<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\CardChecklistItem;
use App\Models\User;
use App\Support\Audit;
use App\Support\BoardWriteGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CardChecklistItemController extends Controller
{
    public function store(Request $request, Card $card): JsonResponse
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
            'text' => ['required', 'string', 'max:5000'],
            'position' => ['nullable', 'numeric'],
        ]);

        $position = array_key_exists('position', $data) && $data['position'] !== null
            ? (float) $data['position']
            : ((float) (CardChecklistItem::query()->where('card_id', $card->id)->max('position') ?? 0) + 100.0);

        $item = CardChecklistItem::query()->create([
            'card_id' => $card->id,
            'text' => $data['text'],
            'is_done' => false,
            'position' => $position,
        ]);

        Audit::log($actor, 'checklist_item_created', 'card', (int) $item->card_id, [
            'item_id' => (int) $item->id,
            'text' => (string) $item->text,
            'text_preview' => mb_strlen($item->text) > 80 ? mb_substr($item->text, 0, 80) . '...' : $item->text,
            'is_done' => (bool) $item->is_done,
        ]);

        return response()->json([
            'data' => [
                'id' => $item->id,
                'card_id' => (int) $item->card_id,
                'text' => $item->text,
                'is_done' => (bool) $item->is_done,
                'position' => (float) $item->position,
            ],
        ], 201);
    }

    public function update(Request $request, CardChecklistItem $item): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless($actor->canWriteCards(), 403);

        $item->loadMissing('card');
        $board = BoardWriteGuard::forCard($item->card);

        if (! $actor->isAdmin()) {
            $isMember = $actor->boards()->where('boards.id', $board->id)->exists();
            abort_unless($isMember, 403);
        }

        $data = $request->validate([
            'text' => ['sometimes', 'required', 'string', 'max:5000'],
            'is_done' => ['sometimes', 'required', 'boolean'],
            'position' => ['sometimes', 'required', 'numeric'],
        ]);

        $before = [
            'text' => (string) $item->text,
            'is_done' => (bool) $item->is_done,
            'position' => (float) $item->position,
        ];

        $item->update($data);

        $textPreview = mb_substr(trim((string) $item->text), 0, 80);
        if (mb_strlen((string) $item->text) > 80) {
            $textPreview .= '...';
        }

        $changes = array_keys($data);

        // Si solo cambió position, podés no auditarlo si no te interesa
        if ($changes !== ['position']) {
            Audit::log($actor, 'checklist_item_updated', 'card', (int) $item->card_id, [
                'item_id' => (int) $item->id,
                'changes' => $changes,
                'text' => (string) $item->text,
                'text_preview' => $textPreview,
                'is_done' => (bool) $item->is_done,
                'before' => [
                    'text' => $before['text'],
                    'is_done' => $before['is_done'],
                ],
            ]);
        }

        return response()->json([
            'data' => [
                'id' => $item->id,
                'card_id' => (int) $item->card_id,
                'text' => $item->text,
                'is_done' => (bool) $item->is_done,
                'position' => (float) $item->position,
            ],
        ]);
    }

    public function destroy(Request $request, CardChecklistItem $item): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless($actor->canDeleteCards(), 403);
        $item->loadMissing('card');
        $board = BoardWriteGuard::forCard($item->card);
        if (! $actor->isAdmin()) {
            $isMember = $actor->boards()->where('boards.id', $board->id)->exists();
            abort_unless($isMember, 403);
        }

        $item->delete();

        $textPreview = mb_substr(trim((string) $item->text), 0, 80);
        if (mb_strlen((string) $item->text) > 80) {
            $textPreview .= '...';
        }

        Audit::log($actor, 'checklist_item_deleted', 'card', (int) $item->card_id, [
            'item_id' => (int) $item->id,
            'text' => (string) $item->text,
            'text_preview' => $textPreview,
        ]);

        return response()->json(null, 204);
    }
}

