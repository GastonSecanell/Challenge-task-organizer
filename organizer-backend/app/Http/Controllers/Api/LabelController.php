<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Board;
use App\Models\Label;
use App\Models\User;
use App\Support\Audit;
use App\Support\BoardWriteGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LabelController extends Controller
{
    public function index(Request $request, Board $board): JsonResponse
    {
        $board->loadMissing('labels');

        return response()->json([
            'data' => $board->labels->map(fn (Label $l) => $this->serialize($l))->values(),
        ]);
    }

    public function store(Request $request, Board $board): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless($actor->canManageBoards(), 403);
        BoardWriteGuard::abortIfArchived($board);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['required', 'string', 'max:32'],
            'position' => ['nullable', 'numeric'],
        ]);

        $position = array_key_exists('position', $data) && $data['position'] !== null
            ? (float) $data['position']
            : ((float) (Label::query()->where('board_id', $board->id)->max('position') ?? 0) + 100.0);

        $label = Label::query()->create([
            'board_id' => $board->id,
            'name' => $data['name'],
            'color' => $data['color'],
            'position' => $position,
        ]);

        Audit::log($actor, 'label_created', 'label', $label->id, [
            'board_id' => (int) $board->id,
            'name' => $label->name,
        ]);

        return response()->json([
            'data' => $this->serialize($label),
        ], 201);
    }

    public function update(Request $request, Label $label): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless($actor->canManageBoards(), 403);
        BoardWriteGuard::forBoardId((int) $label->board_id);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'color' => ['sometimes', 'required', 'string', 'max:32'],
            'position' => ['sometimes', 'required', 'numeric'],
        ]);

        $label->update($data);

        Audit::log($actor, 'label_updated', 'label', $label->id, [
            'changes' => array_keys($data),
        ]);

        return response()->json([
            'data' => $this->serialize($label),
        ]);
    }

    public function destroy(Request $request, Label $label): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless($actor->canManageBoards(), 403);
        BoardWriteGuard::forBoardId((int) $label->board_id);

        $id = (int) $label->id;
        $label->delete();

        Audit::log($actor, 'label_deleted', 'label', $id);

        return response()->json(null, 204);
    }

    private function serialize(Label $l): array
    {
        return [
            'id' => $l->id,
            'board_id' => (int) $l->board_id,
            'name' => $l->name,
            'color' => $l->color,
            'position' => (float) $l->position,
        ];
    }
}

