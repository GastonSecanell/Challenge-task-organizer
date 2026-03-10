<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    private function serializeUser(User $u): array
    {
        $roleId = $u->roleId();
        return [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $roleId, // compat
            'role_id' => $roleId,
            'role_name' => $u->roleName(),
            'roles' => $u->roles->map(fn (Role $r) => [
                'id' => (int) $r->id,
                'slug' => $r->slug,
                'name' => $r->name,
            ])->values(),
            'has_avatar' => (bool) $u->avatar_path,
            'avatar_url' => $u->avatar_path ? "/api/users/{$u->id}/avatar" : null,
            'created_at' => $u->created_at,
        ];
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless($actor->isAdmin(), 403);

        $users = User::query()
            ->select(['id', 'name', 'email', 'role', 'avatar_path', 'created_at'])
            ->with('roles:id,slug,name')
            ->orderBy('id', 'desc')
            ->limit(200)
            ->get();

        return response()->json([
            'data' => $users->map(fn (User $u) => $this->serializeUser($u))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless($actor->isAdmin(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role_id' => ['nullable', 'integer', Rule::exists('roles', 'id')],
            'role' => ['nullable', 'integer', Rule::exists('roles', 'id')], // compat con frontend viejo
            'password' => ['required', 'string', 'min:6', 'max:255'],
        ]);

        $roleId = (int) ($data['role_id'] ?? $data['role'] ?? User::ROLE_OPERADOR);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $roleId, // compat legacy
            'password' => Hash::make($data['password']),
        ]);
        $user->roles()->sync([$roleId]);
        $user->loadMissing('roles:id,slug,name');

        Audit::log($actor, 'user_created', 'user', $user->id, [
            'email' => $user->email,
            'role_id' => $roleId,
            'role_name' => $user->roleName(),
        ]);

        return response()->json([
            'data' => $this->serializeUser($user),
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless($actor->isAdmin(), 403);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['sometimes', 'nullable', 'string', 'min:6', 'max:255'],
            'role_ids' => ['sometimes', 'required', 'array', 'min:1'],
            'role_ids.*' => ['integer', Rule::exists('roles', 'id')],
            'role_id' => ['sometimes', 'nullable', 'integer', Rule::exists('roles', 'id')], // compat
        ]);

        if (!array_key_exists('name', $data) && !array_key_exists('email', $data) && !array_key_exists('password', $data)
            && !array_key_exists('role_ids', $data) && !array_key_exists('role_id', $data)) {
            return response()->json(['message' => 'No hay cambios para actualizar.'], 422);
        }

        $changes = [];

        if (array_key_exists('name', $data)) {
            $user->name = $data['name'];
            $changes[] = 'name';
        }
        if (array_key_exists('email', $data)) {
            $user->email = $data['email'];
            $changes[] = 'email';
        }
        if (array_key_exists('password', $data) && filled($data['password'])) {
            $user->password = $data['password']; // cast hashed
            $changes[] = 'password';
        }

        $roleIds = null;
        if (array_key_exists('role_ids', $data)) {
            $roleIds = collect($data['role_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all();
        } elseif (array_key_exists('role_id', $data) && $data['role_id']) {
            $roleIds = [(int) $data['role_id']];
        }

        if (is_array($roleIds)) {
            if (count($roleIds) === 0) {
                return response()->json(['message' => 'Debe seleccionar al menos un rol.'], 422);
            }
            $user->roles()->sync($roleIds);
            $changes[] = 'roles';

            // Compatibilidad con la columna legacy users.role
            $primaryRoleId = (int) (collect($roleIds)->sort()->first() ?? User::ROLE_OPERADOR);
            $user->role = $primaryRoleId;
            $changes[] = 'role';
        }

        $user->save();
        $user->loadMissing('roles:id,slug,name');

        Audit::log($actor, 'user_updated', 'user', $user->id, [
            'changes' => $changes,
            'role_ids' => is_array($roleIds) ? $roleIds : $user->roles->pluck('id')->map(fn ($id) => (int) $id)->values(),
        ]);

        return response()->json([
            'data' => $this->serializeUser($user),
        ]);
    }
}

