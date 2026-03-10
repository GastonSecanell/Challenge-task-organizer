<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless($actor->canReadAudit(), 403);

        $logs = AuditLog::query()
            ->with(['user:id,name,email,role'])
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return response()->json([
            'data' => $logs,
        ]);
    }
}

