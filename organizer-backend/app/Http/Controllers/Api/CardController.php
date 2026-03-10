<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BoardColumn;
use App\Models\Card;
use App\Models\User;
use App\Support\Audit;
use App\Support\BoardWriteGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CardController extends Controller
{
    public function show(Request $request, Card $card): JsonResponse
    {
        $card->loadMissing([
            'checklistItems',
            'attachments',
            'labels',
            'members',
            'assignee',
            'column',
            'coverAttachment',
        ]);

        $card->loadCount('comments');

        $boardId = (int) $card->column->board_id;

        return response()->json([
            'data' => [
                'id' => $card->id,
                'board_id' => $boardId,
                'column_id' => (int) $card->column_id,
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
                'comments_count' => (int) ($card->comments_count ?? 0),
                'assignee' => $card->assignee ? [
                    'id' => $card->assignee->id,
                    'name' => $card->assignee->name,
                    'email' => $card->assignee->email,
                    'role' => (int) $card->assignee->role,
                    'has_avatar' => (bool) $card->assignee->avatar_path,
                    'avatar_url' => $card->assignee->avatar_path ? "/api/users/{$card->assignee->id}/avatar" : null,
                ] : null,
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
                'position' => (float) $card->position,
                'checklist_items' => $card->checklistItems->map(function ($i) {
                    return [
                        'id' => $i->id,
                        'card_id' => (int) $i->card_id,
                        'text' => $i->text,
                        'is_done' => (bool) $i->is_done,
                        'position' => (float) $i->position,
                    ];
                })->values(),
                'attachments' => $card->attachments->map(function ($a) {
                    $isImage = str_starts_with((string) $a->mime_type, 'image/');

                    return [
                        'id' => $a->id,
                        'card_id' => (int) $a->card_id,
                        'original_name' => $a->original_name,
                        'mime_type' => $a->mime_type,
                        'size' => (int) $a->size,
                        'created_at' => $a->created_at,
                        'thumb_url' => $isImage ? "/api/attachments/{$a->id}/thumb" : null,
                        'preview_url' => $isImage ? "/api/attachments/{$a->id}/preview" : null,
                        'download_url' => "/api/attachments/{$a->id}/download",
                    ];
                })->values(),
                'labels' => $card->labels->map(function ($l) {
                    return [
                        'id' => $l->id,
                        'name' => $l->name,
                        'color' => $l->color,
                    ];
                })->values(),
                'label_ids' => $card->labels->pluck('id')->values(),
            ],
        ]);
    }

    public function move(Request $request, Card $card): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        abort_unless($actor->canWriteCards(), 403);

        $board = BoardWriteGuard::forCard($card);

        if (! $actor->isAdmin()) {
            $isMember = $actor->boards()->where('boards.id', $board->id)->exists();
            abort_unless($isMember, 403);
        }

        $data = $request->validate([
            'column_id' => ['required', 'integer', Rule::exists('columns', 'id')],
            'position' => ['required', 'numeric'],
        ]);

        $destinationColumn = BoardColumn::query()->findOrFail($data['column_id']);

        $card->loadMissing('column');

        if ((int) $card->column->board_id !== (int) $destinationColumn->board_id) {
            return response()->json([
                'message' => 'Destination column belongs to a different board.',
            ], 422);
        }

        $fromColumnId = (int) $card->column_id;

        $card->update([
            'column_id' => $destinationColumn->id,
            'position' => $data['position'],
        ]);

        Audit::log($actor, 'card_moved', 'card', $card->id, [
            'from_column_id' => $fromColumnId,
            'to_column_id' => (int) $destinationColumn->id,
            'to_column_name' => $destinationColumn->name,
            'from_column_name' => $card->column->name,
            'position' => (float) $card->position,
        ]);

        return response()->json([
            'data' => [
                'id' => $card->id,
                'column_id' => $card->column_id,
                'position' => (float) $card->position,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        abort_unless($actor->canWriteCards(), 403);

        $data = $request->validate([
            'column_id' => ['required', 'integer', Rule::exists('columns', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_at' => ['nullable', 'date'],
            'cover_color' => ['nullable', 'string', 'max:32'],
            'cover_attachment_id' => ['nullable', 'integer', Rule::exists('card_attachments', 'id')],
            'cover_size' => ['nullable', 'string', Rule::in(['small', 'large'])],
            'assigned_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'is_done' => ['nullable', 'boolean'],
            'position' => ['nullable', 'numeric'],
        ]);

        $columnId = (int) $data['column_id'];
        $col = BoardColumn::query()->findOrFail($columnId);
        $board = BoardWriteGuard::forColumn($col);

        if (! $actor->isAdmin()) {
            $isMember = $actor->boards()->where('boards.id', $board->id)->exists();
            abort_unless($isMember, 403);
        }

        $position = array_key_exists('position', $data) && $data['position'] !== null
            ? (float) $data['position']
            : ((float) (Card::query()->where('column_id', $columnId)->max('position') ?? 0) + 100.0);

        $coverAttachmentId = array_key_exists('cover_attachment_id', $data)
            ? (int) ($data['cover_attachment_id'] ?? 0)
            : 0;

        if ($coverAttachmentId) {
            $coverAttachmentId = 0;
        }

        $card = Card::query()->create([
            'column_id' => $columnId,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'due_at' => $data['due_at'] ?? null,
            'cover_color' => $data['cover_color'] ?? null,
            'cover_attachment_id' => $coverAttachmentId ?: null,
            'cover_size' => $data['cover_size'] ?? 'small',
            'assigned_user_id' => $data['assigned_user_id'] ?? null,
            'is_done' => (bool) ($data['is_done'] ?? false),
            'position' => $position,
        ]);

        Audit::log($actor, 'card_created', 'card', $card->id, [
            'column_id' => (int) $card->column_id,
        ]);

        return response()->json([
            'data' => [
                'id' => $card->id,
                'column_id' => $card->column_id,
                'title' => $card->title,
                'description' => $card->description,
                'due_at' => $card->due_at?->toISOString(),
                'cover_color' => $card->cover_color,
                'cover_attachment_id' => $card->cover_attachment_id,
                'cover_attachment' => null,
                'cover_size' => $card->cover_size ?? 'small',
                'assigned_user_id' => $card->assigned_user_id,
                'is_done' => (bool) $card->is_done,
                'position' => (float) $card->position,
            ],
        ], 201);
    }

    public function update(Request $request, Card $card): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        abort_unless($actor->canWriteCards(), 403);

        $board = BoardWriteGuard::forCard($card);

        if (! $actor->isAdmin()) {
            $isMember = $actor->boards()->where('boards.id', $board->id)->exists();
            abort_unless($isMember, 403);
        }

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'due_at' => ['sometimes', 'nullable', 'date'],
            'cover_color' => ['sometimes', 'nullable', 'string', 'max:32'],
            'cover_attachment_id' => ['sometimes', 'nullable', 'integer', Rule::exists('card_attachments', 'id')],
            'cover_size' => ['sometimes', 'required', 'string', Rule::in(['small', 'large'])],
            'assigned_user_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')],
            'is_done' => ['sometimes', 'required', 'boolean'],
        ]);

        $beforeIsDone = (bool) $card->is_done;

        if (array_key_exists('cover_attachment_id', $data) && $data['cover_attachment_id'] !== null) {
            $card->loadMissing('attachments');

            $aid = (int) $data['cover_attachment_id'];
            $belongs = $card->attachments->firstWhere('id', $aid) !== null;

            if (! $belongs) {
                return response()->json([
                    'message' => 'Cover attachment does not belong to this card.',
                ], 422);
            }

            $data['cover_color'] = null;
        }

        if (array_key_exists('cover_color', $data) && $data['cover_color'] !== null) {
            $data['cover_attachment_id'] = null;
        }

        $card->update($data);

        if (array_key_exists('is_done', $data)) {
            $afterIsDone = (bool) $card->is_done;

            if ($beforeIsDone !== $afterIsDone) {
                Audit::log(
                    $actor,
                    $afterIsDone ? 'card_completed' : 'card_marked_incomplete',
                    'card',
                    $card->id,
                    [
                        'is_done' => $afterIsDone,
                    ]
                );
            }
        }

        $payload = array_intersect_key($data, array_flip([
            'title',
            'description',
            'due_at',
            'cover_color',
            'cover_attachment_id',
            'cover_size',
            'assigned_user_id',
        ]));

        if (! empty($payload)) {
            Audit::log($actor, 'card_updated', 'card', $card->id, $payload);
        }

        $card->load('coverAttachment');

        return response()->json([
            'data' => [
                'id' => $card->id,
                'column_id' => $card->column_id,
                'title' => $card->title,
                'description' => $card->description,
                'due_at' => $card->due_at?->toISOString(),
                'cover_color' => $card->cover_color,
                'cover_attachment_id' => $card->cover_attachment_id,
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
                'cover_size' => $card->cover_size ?? 'small',
                'assigned_user_id' => $card->assigned_user_id,
                'is_done' => (bool) $card->is_done,
                'position' => (float) $card->position,
            ],
        ]);
    }

    public function destroy(Card $card): JsonResponse
    {
        /** @var User $actor */
        $actor = request()->user();

        abort_unless($actor->canDeleteCards(), 403);

        $board = BoardWriteGuard::forCard($card);

        if (! $actor->isAdmin()) {
            $isMember = $actor->boards()->where('boards.id', $board->id)->exists();
            abort_unless($isMember, 403);
        }

        $id = $card->id;

        $card->delete();

        Audit::log($actor, 'card_deleted', 'card', (int) $id);

        return response()->json(null, 204);
    }
}
