<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BoardColumn;
use App\Models\User;
use App\Support\Audit;
use App\Support\BoardWriteGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ColumnController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless($actor->canManageColumns(), 403);

        $data = $request->validate([
            'board_id' => ['required', 'integer', Rule::exists('boards', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'position' => ['nullable', 'numeric'],
        ]);

        $boardId = (int) $data['board_id'];
        BoardWriteGuard::forBoardId($boardId);
        if (! $actor->isAdmin()) {
            $isMember = $actor->boards()->where('boards.id', $boardId)->exists();
            abort_unless($isMember, 403);
        }

        $position = array_key_exists('position', $data) && $data['position'] !== null
            ? (float) $data['position']
            : ((float) (BoardColumn::query()->where('board_id', $boardId)->max('position') ?? 0) + 100.0);

        $column = BoardColumn::query()->create([
            'board_id' => $boardId,
            'name' => $data['name'],
            'position' => $position,
        ]);

        Audit::log($actor, 'column_created', 'column', $column->id, [
            'board_id' => (int) $column->board_id,
            'name' => $column->name,
        ]);

        return response()->json([
            'data' => [
                'id' => $column->id,
                'board_id' => (int) $column->board_id,
                'name' => $column->name,
                'position' => (float) $column->position,
            ],
        ], 201);
    }

    public function update(Request $request, BoardColumn $column): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless($actor->canManageColumns(), 403);
        $board = BoardWriteGuard::forColumn($column);
        if (! $actor->isAdmin()) {
            $isMember = $actor->boards()->where('boards.id', $board->id)->exists();
            abort_unless($isMember, 403);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'position' => ['sometimes', 'required', 'numeric'],
        ]);

        $before = [
            'name' => $column->name,
            'position' => (float) $column->position,
        ];

        $column->update($data);

        Audit::log($actor, 'column_updated', 'column', (int) $column->id, [
            'board_id' => (int) $column->board_id,
            'from' => $before,
            'to' => [
                'name' => $column->name,
                'position' => (float) $column->position,
            ],
        ]);

        return response()->json([
            'data' => [
                'id' => $column->id,
                'board_id' => (int) $column->board_id,
                'name' => $column->name,
                'position' => (float) $column->position,
            ],
        ]);
    }

    public function destroy(Request $request, BoardColumn $column): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless($actor->canManageColumns(), 403);
        $board = BoardWriteGuard::forColumn($column);
        if (! $actor->isAdmin()) {
            $isMember = $actor->boards()->where('boards.id', $board->id)->exists();
            abort_unless($isMember, 403);
        }

        $column->loadCount('cards');
        $payload = [
            'board_id' => (int) $column->board_id,
            'name' => $column->name,
            'cards_count' => (int) $column->cards_count,
        ];

        $id = (int) $column->id;
        $column->delete();

        Audit::log($actor, 'column_deleted', 'column', $id, $payload);

        return response()->json(null, 204);
    }
}

