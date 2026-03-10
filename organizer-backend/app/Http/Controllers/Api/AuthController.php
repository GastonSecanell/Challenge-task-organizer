<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate(
            [
                'email' => ['required', 'email'],
                'password' => ['required', 'string'],
                'device_name' => ['nullable', 'string', 'max:255'],
            ],
            [
                'email.required' => 'Debes ingresar tu correo electrónico.',
                'email.email' => 'Debes ingresar un correo electrónico válido.',
                'password.required' => 'Debes ingresar tu contraseña.',
                'password.string' => 'La contraseña no es válida.',
                'device_name.string' => 'El nombre del dispositivo no es válido.',
                'device_name.max' => 'El nombre del dispositivo no debe superar los :max caracteres.',
            ],
            [
                'email' => 'correo electrónico',
                'password' => 'contraseña',
                'device_name' => 'dispositivo',
            ]
        );

        if (! Auth::attempt(['email' => $data['email'], 'password' => $data['password']])) {
            return response()->json(['message' => 'Credenciales inválidas.'], 422);
        }

        /** @var \App\Models\User $user */
        $user = $request->user();
        $user->loadMissing('roles:id,slug,name');

        $deviceName = $data['device_name'] ?? 'web';
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->roleId(),
                'role_id' => $user->roleId(),
                'role_name' => $user->roleName(),
                'roles' => $user->roles->map(fn ($r) => [
                    'id' => (int) $r->id,
                    'slug' => $r->slug,
                    'name' => $r->name,
                ])->values(),
                'has_avatar' => (bool) $user->avatar_path,
                'avatar_url' => $user->avatar_path ? "/api/users/{$user->id}/avatar" : null,
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $user->loadMissing('roles:id,slug,name');

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->roleId(),
                'role_id' => $user->roleId(),
                'role_name' => $user->roleName(),
                'roles' => $user->roles->map(fn ($r) => [
                    'id' => (int) $r->id,
                    'slug' => $r->slug,
                    'name' => $r->name,
                ])->values(),
                'has_avatar' => (bool) $user->avatar_path,
                'avatar_url' => $user->avatar_path ? "/api/users/{$user->id}/avatar" : null,
            ],
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate(
            [
                'current_password' => ['required', 'string'],
                'new_password' => ['required', 'string', 'min:8', 'confirmed'],
            ],
            [
                'current_password.required' => 'Debes ingresar tu contraseña actual.',
                'current_password.string' => 'La contraseña actual no es válida.',

                'new_password.required' => 'Debes ingresar una nueva contraseña.',
                'new_password.string' => 'La nueva contraseña no es válida.',
                'new_password.min' => 'La nueva contraseña debe tener al menos :min caracteres.',
                'new_password.confirmed' => 'La confirmación de la nueva contraseña no coincide.',
            ],
            [
                'current_password' => 'contraseña actual',
                'new_password' => 'nueva contraseña',
            ]
        );

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['La contraseña actual es incorrecta.'],
            ]);
        }

        $user->password = Hash::make($data['new_password']);
        $user->save();

        return response()->json([
            'message' => 'Contraseña actualizada correctamente.',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['ok' => true]);
    }
}
