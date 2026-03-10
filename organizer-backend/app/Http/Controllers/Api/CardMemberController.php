<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\User;
use App\Support\Audit;
use App\Support\BoardWriteGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CardMemberController extends Controller
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
            // Permitir array vacío para "quitar todos"
            'user_ids' => ['present', 'array'],
            'user_ids.*' => ['integer', Rule::exists('users', 'id')],
        ]);

        $card->loadMissing('column');
        $boardId = (int) $card->column->board_id;

        $userIds = array_values(array_unique(array_map('intval', $data['user_ids'])));

        // Validar que todos los usuarios pertenezcan al board (miembros del proyecto)
        $countSameBoard = DB::table('board_user')
            ->where('board_id', $boardId)
            ->whereIn('user_id', $userIds)
            ->count();

        if ($countSameBoard !== count($userIds)) {
            return response()->json(['message' => 'One or more users do not belong to this board.'], 422);
        }

        $card->members()->sync($userIds);

        Audit::log($actor, 'card_members_updated', 'card', $card->id, [
            'user_ids' => $userIds,
        ]);

        return response()->json([
            'data' => [
                'card_id' => $card->id,
                'user_ids' => $userIds,
            ],
        ]);
    }
}

