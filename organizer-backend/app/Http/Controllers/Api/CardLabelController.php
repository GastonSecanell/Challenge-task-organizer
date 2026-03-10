<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\Label;
use App\Models\User;
use App\Support\Audit;
use App\Support\BoardWriteGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CardLabelController extends Controller
{
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
            // Permitir array vacío para "quitar todas"
            'label_ids' => ['present', 'array'],
            'label_ids.*' => ['integer', Rule::exists('labels', 'id')],
        ]);

        $card->loadMissing('column');
        $boardId = (int) $card->column->board_id;

        $labelIds = array_values(array_unique(array_map('intval', $data['label_ids'])));

        // Validar que todas las labels pertenezcan al mismo board
        $countSameBoard = Label::query()->where('board_id', $boardId)->whereIn('id', $labelIds)->count();
        if ($countSameBoard !== count($labelIds)) {
            return response()->json(['message' => 'One or more labels do not belong to this board.'], 422);
        }

        $card->labels()->sync($labelIds);

        Audit::log($actor, 'card_labels_updated', 'card', $card->id, [
            'label_ids' => $labelIds,
        ]);

        return response()->json([
            'data' => [
                'card_id' => $card->id,
                'label_ids' => $labelIds,
            ],
        ]);
    }
}

