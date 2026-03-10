<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $roles = Role::query()
            ->select(['id', 'slug', 'name', 'description'])
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $roles->map(fn (Role $r) => [
                'id' => (int) $r->id,
                'slug' => $r->slug,
                'name' => $r->name,
                'description' => $r->description,
            ])->values(),
        ]);
    }
}

