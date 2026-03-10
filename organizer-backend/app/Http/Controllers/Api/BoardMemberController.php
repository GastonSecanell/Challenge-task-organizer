<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Board;
use App\Models\User;
use App\Support\Audit;
use App\Support\BoardWriteGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class BoardMemberController extends Controller
{
    private const ROLE_AUDIT = 4;

    private function abortUnlessCanReadMembers(User $actor, Board $board): void
    {
        if ($actor->isAdmin()) return;

        $isMember = $actor->boards()->where('boards.id', $board->id)->exists();
        abort_unless($isMember, 403);
    }

    private function abortUnlessCanMutateMembers(User $actor, Board $board): void
    {
        // auditoría: solo lectura
        abort_if((int) $actor->role === self::ROLE_AUDIT, 403);

        // admin ok
        if ($actor->isAdmin()) return;

        // no admin: debe ser miembro
        $isMember = $actor->boards()->where('boards.id', $board->id)->exists();
        abort_unless($isMember, 403);
    }

    public function options(Request $request, Board $board): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $this->abortUnlessCanMutateMembers($actor, $board);
        BoardWriteGuard::abortIfArchived($board);

        $users = User::query()
            ->select(['id', 'name', 'email', 'role', 'avatar_path'])
            ->orderBy('name')
            ->limit(300)
            ->get();

        return response()->json([
            'data' => $users->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => (int) $u->role,
                'has_avatar' => (bool) $u->avatar_path,
                'is_owner' => (int) $u->id === (int) $board->owner_id,
                'avatar_url' => $u->avatar_path ? "/api/users/{$u->id}/avatar" : null,
            ])->values(),
        ]);
    }

    public function index(Request $request, Board $board): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $this->abortUnlessCanReadMembers($actor, $board);

        $board->loadMissing('members');

        return response()->json([
            'data' => $board->members->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => (int) $u->role,
                'has_avatar' => (bool) $u->avatar_path,
                'is_owner' => (int) $u->id === (int) $board->owner_id,
                'avatar_url' => $u->avatar_path ? "/api/users/{$u->id}/avatar" : null,
            ])->values(),
        ]);
    }

    public function store(Request $request, Board $board): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $this->abortUnlessCanMutateMembers($actor, $board);
        BoardWriteGuard::abortIfArchived($board);

        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
        ]);

        $userId = (int) $data['user_id'];

        // (opcional) evitar duplicar: syncWithoutDetaching ya lo hace bien
        $board->members()->syncWithoutDetaching([$userId]);

        Audit::log($actor, 'board_member_added', 'board', $board->id, [
            'user_id' => $userId,
        ]);

        return response()->json(['ok' => true], 201);
    }

    public function destroy(Request $request, Board $board, User $user): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $this->abortUnlessCanMutateMembers($actor, $board);
        BoardWriteGuard::abortIfArchived($board);

        // No se puede eliminar al propietario
        if ((int) $board->owner_id === (int) $user->id) {
            abort(422, 'No se puede eliminar al propietario del proyecto.');
        }

        DB::transaction(function () use ($board, $user) {
            // ids de cards del board (via columns)
            $cardIdsQuery = DB::table('cards as c')
                ->select('c.id')
                ->join('columns as col', 'col.id', '=', 'c.column_id')
                ->where('col.board_id', $board->id);

            // 1) Sacarlo de miembros de tarjeta (card_user)
            DB::table('card_user')
                ->where('user_id', $user->id)
                ->whereIn('card_id', $cardIdsQuery)
                ->delete();

            // 2) Si estaba asignado como responsable, limpiarlo
            DB::table('cards')
                ->where('assigned_user_id', $user->id)
                ->whereIn('id', DB::table('cards as c')
                    ->select('c.id')
                    ->join('columns as col', 'col.id', '=', 'c.column_id')
                    ->where('col.board_id', $board->id))
                ->update(['assigned_user_id' => null]);

            // 3) Sacarlo del board
            $board->members()->detach($user->id);
        });

        Audit::log($actor, 'board_member_removed', 'board', $board->id, [
            'user_id' => (int) $user->id,
            'removed_from_cards' => true,
            'unassigned_cards' => true,
        ]);

        return response()->json(null, 204);
    }
}

