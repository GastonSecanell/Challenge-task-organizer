<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\CardComment;
use App\Models\User;
use App\Support\Audit;
use App\Support\BoardWriteGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CardCommentController extends Controller
{
    private function serializeComment(CardComment $c): array
    {
        return [
            'id' => $c->id,
            'card_id' => (int) $c->card_id,
            'parent_id' => $c->parent_id ? (int) $c->parent_id : null,
            'body' => $c->body,
            'created_at' => $c->created_at,
            'user' => $c->user ? [
                'id' => $c->user->id,
                'name' => $c->user->name,
                'email' => $c->user->email,
                'role' => (int) $c->user->role,
                'has_avatar' => (bool) $c->user->avatar_path,
                'avatar_url' => $c->user->avatar_path ? "/api/users/{$c->user->id}/avatar" : null,
            ] : null,
            'parent_comment' => $c->parent ? [
                'id' => $c->parent->id,
                'body' => $c->parent->body,
                'user' => $c->parent->user ? [
                    'id' => $c->parent->user->id,
                    'name' => $c->parent->user->name,
                ] : null,
            ] : null,
        ];
    }

    public function index(Request $request, Card $card): JsonResponse
    {
        // lectura permitida a cualquier autenticado
        $comments = CardComment::query()
            ->where('card_id', $card->id)
            ->with([
                'user:id,name,email,role,avatar_path',
                'parent:id,user_id,body',
                'parent.user:id,name',
            ])
            ->orderBy('id', 'desc')
            ->limit(200)
            ->get();

        return response()->json([
            'data' => $comments->map(fn (CardComment $c) => $this->serializeComment($c))->values(),
        ]);
    }

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
            'body' => ['required', 'string', 'max:5000'],
            'parent_id' => ['nullable', 'integer', 'exists:card_comments,id'],
        ]);

        $parentId = (int) ($data['parent_id'] ?? 0);
        if ($parentId) {
            $parent = CardComment::query()->findOrFail($parentId);
            if ((int) $parent->card_id !== (int) $card->id) {
                return response()->json(['message' => 'Parent comment does not belong to this card.'], 422);
            }
        }

        $comment = CardComment::query()->create([
            'card_id' => (int) $card->id,
            'user_id' => (int) $actor->id,
            'parent_id' => $parentId ?: null,
            'body' => $data['body'],
        ]);

        Audit::log($actor, 'card_commented', 'card', (int) $card->id, [
            'comment_id' => (int) $comment->id,
            'parent_id' => $parentId ?: null,
        ]);

        $comment->load([
            'user:id,name,email,role,avatar_path',
            'parent:id,user_id,body',
            'parent.user:id,name',
        ]);

        return response()->json([
            'data' => $this->serializeComment($comment),
        ], 201);
    }

    public function destroy(Request $request, Card $card, CardComment $comment): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless($actor->canDeleteCards(), 403);
        $board = BoardWriteGuard::forCard($card);
        if (! $actor->isAdmin()) {
            $isMember = $actor->boards()->where('boards.id', $board->id)->exists();
            abort_unless($isMember, 403);
        }

        if ((int) $comment->card_id !== (int) $card->id) {
            return response()->json(['message' => 'Comment does not belong to this card.'], 422);
        }

        $isOwner = (int) $comment->user_id === (int) $actor->id;
        abort_unless($isOwner || $actor->isAdmin(), 403);

        $id = (int) $comment->id;
        $comment->delete();

        Audit::log($actor, 'card_comment_deleted', 'card', (int) $card->id, [
            'comment_id' => $id,
        ]);

        return response()->json(null, 204);
    }
}

