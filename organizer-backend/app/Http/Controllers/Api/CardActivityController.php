<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Card;
use App\Models\CardComment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CardActivityController extends Controller
{
    private const VISIBLE_AUDIT_ACTIONS = [
        'card_moved',
        'card_completed',
        'card_marked_incomplete',
        'checklist_item_created',
        'checklist_item_updated',
        'checklist_item_deleted',
        'attachment_uploaded',
        'attachment_deleted',
    ];

    public function index(Request $request, Card $card): JsonResponse
    {
        $audit = AuditLog::query()
            ->where('entity_type', 'card')
            ->where('entity_id', (int) $card->id)
            ->whereIn('action', self::VISIBLE_AUDIT_ACTIONS)
            ->with('user:id,name,email,role,avatar_path')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(function (AuditLog $a) {
                return [
                    'type' => 'audit',
                    'id' => (int) $a->id,
                    'action' => $a->action,
                    'payload' => $a->payload,
                    'created_at' => $a->created_at,
                    'user' => $this->mapUser($a->user),
                ];
            })
            ->filter(fn (array $a) => $this->shouldKeepAuditItem($a))
            ->values();

        $comments = CardComment::query()
            ->where('card_id', (int) $card->id)
            ->with([
                'user:id,name,email,role,avatar_path',
                'parent:id,user_id,body',
                'parent.user:id,name',
            ])
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(function (CardComment $c) {
                return [
                    'type' => 'comment',
                    'id' => (int) $c->id,
                    'parent_id' => $c->parent_id ? (int) $c->parent_id : null,
                    'body' => $c->body,
                    'created_at' => $c->created_at,
                    'user' => $this->mapUser($c->user),
                    'parent_comment' => $c->parent ? [
                        'id' => (int) $c->parent->id,
                        'body' => $c->parent->body,
                        'user' => $c->parent->user ? [
                            'id' => (int) $c->parent->user->id,
                            'name' => $c->parent->user->name,
                        ] : null,
                    ] : null,
                ];
            });

        $items = $audit
            ->concat($comments)
            ->sortByDesc(fn ($item) => $item['created_at'] ?? null)
            ->values();

        return response()->json([
            'data' => $items,
        ]);
    }

    private function shouldKeepAuditItem(array $item): bool
    {
        if (($item['action'] ?? null) !== 'card_moved') {
            return true;
        }

        $payload = $item['payload'] ?? [];
        $from = (int) ($payload['from_column_id'] ?? 0);
        $to = (int) ($payload['to_column_id'] ?? 0);

        // Solo mostrar movimientos reales entre columnas
        return $from > 0 && $to > 0 && $from !== $to;
    }

    private function mapUser(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id' => (int) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => (int) $user->role,
            'has_avatar' => (bool) $user->avatar_path,
            'avatar_url' => $user->avatar_path ? "/api/users/{$user->id}/avatar" : null,
        ];
    }
}
