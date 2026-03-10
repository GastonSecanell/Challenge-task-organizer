<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Board;
use App\Models\User;
use App\Support\Audit;
use App\Support\BoardWriteGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BoardController extends Controller
{
    private const ROLE_AUDIT = 4;

    /**
     * Reglas:
     * - Auditoría (role=4): solo lectura (no puede mutar boards)
     * - Admin: todo
     * - Resto: puede mutar si es miembro
     */
    private function abortUnlessCanMutateBoard(User $actor, Board $board): void
    {
        // Auditoría: no puede modificar nada
        abort_if((int) $actor->role === self::ROLE_AUDIT, 403);

        // Admin ok
        if ($actor->isAdmin()) {
            return;
        }

        // No admin: debe ser miembro
        $isMember = $actor->boards()->where('boards.id', $board->id)->exists();
        abort_unless($isMember, 403);
    }

    private function abortUnlessCanReadBoard(User $actor, Board $board): void
    {
        if ($actor->isAdmin()) return;

        $isMember = $actor->boards()->where('boards.id', $board->id)->exists();
        abort_unless($isMember, 403);
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $archived = (bool) $request->boolean('archived', false);

        $query = Board::query()
            ->select(['boards.id', 'boards.name', 'boards.archived_at', 'boards.created_at', 'boards.owner_id'])
            ->selectSub(function ($sub) use ($actor) {
                $sub->from('board_user')
                    ->select('is_favorite')
                    ->whereColumn('board_user.board_id', 'boards.id')
                    ->where('board_user.user_id', $actor->id)
                    ->limit(1);
            }, 'is_favorite')
            ->when(! $archived, fn ($q) => $q->whereNull('boards.archived_at'))
            ->when($archived, fn ($q) => $q->whereNotNull('boards.archived_at'));

        // Admin ve todo. Resto solo los que pertenece.
        if (! $actor->isAdmin()) {
            $query->whereHas('members', fn ($q) => $q->where('users.id', $actor->id));
        }

        $boards = $query
            ->orderByDesc('is_favorite')
            ->orderByDesc('boards.id')
            ->get();

        return response()->json([
            'data' => $boards->map(fn (Board $b) => [
                'id'          => $b->id,
                'name'        => $b->name,
                'archived_at' => $b->archived_at?->toISOString(),
                'is_favorite' => (bool) ($b->is_favorite ?? false),
                'is_owner'    => (int) $b->owner_id === (int) $actor->id,
                'created_at'  => $b->created_at,
            ])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        // Auditoría no puede crear (si canManageBoards no lo contempla, lo frenamos acá)
        abort_if((int) $actor->role === self::ROLE_AUDIT, 403);
        abort_unless($actor->canManageBoards(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $board = DB::transaction(function () use ($actor, $data) {
            $board = Board::query()->create([
                'name' => $data['name'],
                'owner_id' => $actor->id,
            ]);

            $board->members()->syncWithoutDetaching([
                $actor->id => ['is_favorite' => false],
            ]);

            return $board;
        });

        Audit::log($actor, 'board_created', 'board', $board->id, [
            'name' => $board->name,
        ]);

        return response()->json([
            'data' => [
                'id'          => $board->id,
                'name'        => $board->name,
                'archived_at' => $board->archived_at?->toISOString(),
                'is_favorite' => false,
                'is_owner'    => true,
                'created_at'  => $board->created_at,
            ],
        ], 201);
    }

    public function archive(Request $request, Board $board): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $this->abortUnlessCanMutateBoard($actor, $board);

        $board->update(['archived_at' => now()]);

        Audit::log($actor, 'board_archived', 'board', (int) $board->id);

        return response()->json([
            'data' => [
                'id' => $board->id,
                'archived_at' => $board->archived_at?->toISOString(),
            ],
        ]);
    }

    public function unarchive(Request $request, Board $board): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $this->abortUnlessCanMutateBoard($actor, $board);

        $board->update(['archived_at' => null]);

        Audit::log($actor, 'board_unarchived', 'board', (int) $board->id);

        return response()->json([
            'data' => [
                'id' => $board->id,
                'archived_at' => null,
            ],
        ]);
    }

    public function favorite(Request $request, Board $board): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $data = $request->validate([
            'is_favorite' => ['required', 'boolean'],
        ]);

        // Debe ser miembro (admin incluido: si querés permitir favoritos para admin sin membresía, lo cambiamos)
        $isMember = $actor->boards()->where('boards.id', $board->id)->exists();
        abort_unless($isMember, 403);

        $actor->boards()->updateExistingPivot($board->id, ['is_favorite' => (bool) $data['is_favorite']]);

        Audit::log($actor, 'board_favorite_updated', 'board', (int) $board->id, [
            'is_favorite' => (bool) $data['is_favorite'],
        ]);

        return response()->json([
            'data' => [
                'board_id' => (int) $board->id,
                'is_favorite' => (bool) $data['is_favorite'],
            ],
        ]);
    }

    public function destroy(Request $request, Board $board): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        // Solo admin puede borrar definitivo
        abort_unless($actor->isAdmin(), 403);

        // Solo permitir borrar definitivo si está archivado
        abort_unless($board->archived_at !== null, 422);

        $id = (int) $board->id;
        $name = $board->name;

        $board->delete();

        Audit::log($actor, 'board_deleted', 'board', $id, ['name' => $name]);

        return response()->json(null, 204);
    }

    public function update(Request $request, Board $board): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $this->abortUnlessCanMutateBoard($actor, $board);

        BoardWriteGuard::abortIfArchived($board);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $oldName = $board->name;

        $board->update([
            'name' => $data['name'],
        ]);

        Audit::log($actor, 'board_renamed', 'board', $board->id, [
            'from' => $oldName,
            'to' => $board->name,
        ]);

        return response()->json([
            'data' => [
                'id' => $board->id,
                'name' => $board->name,
                'created_at' => $board->created_at,
            ],
        ]);
    }

    public function show(Request $request, Board $board): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $this->abortUnlessCanReadBoard($actor, $board);

        $board->load([
            'labels',
            'members',
            'columns' => function ($q) {
                $q->with([
                    'cards' => function ($q2) {
                        $q2->with(['labels', 'members', 'assignee', 'coverAttachment'])
                            ->withCount([
                                'attachments',
                                'comments',
                                'checklistItems',
                                'checklistItems as checklist_done_count' => function ($qq) {
                                    $qq->where('is_done', true);
                                },
                            ]);
                    },
                ]);
            },
        ]);

        return response()->json([
            'data' => [
                'id' => $board->id,
                'name' => $board->name,
                'archived_at' => $board->archived_at?->toISOString(),
                'labels' => $board->labels->map(function ($l) {
                    return [
                        'id' => $l->id,
                        'name' => $l->name,
                        'color' => $l->color,
                        'position' => (float) $l->position,
                    ];
                })->values(),
                'members' => $board->members->map(function ($u) {
                    return [
                        'id' => $u->id,
                        'name' => $u->name,
                        'email' => $u->email,
                        'role' => (int) $u->role,
                        'has_avatar' => (bool) $u->avatar_path,
                        'avatar_url' => $u->avatar_path ? "/api/users/{$u->id}/avatar" : null,
                    ];
                })->values(),
                'columns' => $board->columns->map(function ($col) {
                    return [
                        'id' => $col->id,
                        'board_id' => $col->board_id,
                        'name' => $col->name,
                        'position' => (float) $col->position,
                        'cards' => $col->cards->map(function ($card) {
                            return [
                                'id' => $card->id,
                                'column_id' => $card->column_id,
                                'title' => $card->title,
                                'description' => $card->description,
                                'due_at' => $card->due_at?->toISOString(),
                                'cover_color' => $card->cover_color,
                                'cover_attachment_id' => $card->cover_attachment_id,
                                'cover_size' => $card->cover_size ?? 'small',
                                'cover_attachment' => $card->coverAttachment ? [
                                    'id' => (int) $card->coverAttachment->id,
                                    'mime_type' => $card->coverAttachment->mime_type,
                                    'thumb_url' => str_starts_with((string) $card->coverAttachment->mime_type, 'image/')
                                        ? "/api/attachments/{$card->coverAttachment->id}/thumb"
                                        : null,
                                    'preview_url' => str_starts_with((string) $card->coverAttachment->mime_type, 'image/')
                                        ? "/api/attachments/{$card->coverAttachment->id}/preview"
                                        : null,
                                    'download_url' => "/api/attachments/{$card->coverAttachment->id}/download",
                                ] : null,
                                'assigned_user_id' => $card->assigned_user_id,
                                'is_done' => (bool) $card->is_done,
                                'attachments_count' => (int) ($card->attachments_count ?? 0),
                                'comments_count' => (int) ($card->comments_count ?? 0),
                                'checklist_items_count' => (int) ($card->checklist_items_count ?? 0),
                                'checklist_done_count' => (int) ($card->checklist_done_count ?? 0),
                                'position' => (float) $card->position,
                                'labels' => $card->labels->map(function ($l) {
                                    return [
                                        'id' => $l->id,
                                        'name' => $l->name,
                                        'color' => $l->color,
                                    ];
                                })->values(),
                                'label_ids' => $card->labels->pluck('id')->values(),
                                'members' => $card->members->map(function ($u) {
                                    return [
                                        'id' => $u->id,
                                        'name' => $u->name,
                                        'email' => $u->email,
                                        'role' => (int) $u->role,
                                        'has_avatar' => (bool) $u->avatar_path,
                                        'avatar_url' => $u->avatar_path ? "/api/users/{$u->id}/avatar" : null,
                                    ];
                                })->values(),
                                'member_ids' => $card->members->pluck('id')->values(),
                                'assignee' => $card->assignee ? [
                                    'id' => $card->assignee->id,
                                    'name' => $card->assignee->name,
                                    'email' => $card->assignee->email,
                                    'has_avatar' => (bool) $card->assignee->avatar_path,
                                    'avatar_url' => $card->assignee->avatar_path ? "/api/users/{$card->assignee->id}/avatar" : null,
                                ] : null,
                            ];
                        })->values(),
                    ];
                })->values(),
            ],
        ]);
    }
    public function transferOwner(Request $request, Board $board): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        // Auditoría: solo lectura
        abort_if((int) $actor->role === 4, 403);

        // Solo admin o owner actual
        $canTransfer = $actor->isAdmin() || ((int) $board->owner_id === (int) $actor->id);
        abort_unless($canTransfer, 403);

        BoardWriteGuard::abortIfArchived($board);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $newOwnerId = (int) $data['user_id'];

        // No transferir al mismo
        abort_if((int) $board->owner_id === $newOwnerId, 422, 'El usuario ya es el propietario del proyecto.');

        // Debe ser miembro del proyecto
        $isMember = $board->members()->where('users.id', $newOwnerId)->exists();
        abort_unless($isMember, 422, 'El usuario debe ser miembro del proyecto para poder ser propietario.');

        $oldOwnerId = (int) $board->owner_id;

        DB::transaction(function () use ($board, $newOwnerId, $oldOwnerId) {
            // asegurar que el nuevo owner esté en members (por las dudas)
            $board->members()->syncWithoutDetaching([$newOwnerId]);

            // actualizar owner
            $board->update([
                'owner_id' => $newOwnerId,
            ]);
        });

        Audit::log($actor, 'board_owner_transferred', 'board', (int) $board->id, [
            'from_user_id' => $oldOwnerId,
            'to_user_id' => $newOwnerId,
        ]);

        return response()->json([
            'data' => [
                'board_id' => (int) $board->id,
                'owner_id' => (int) $board->owner_id,
            ],
        ]);
    }
}
