<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private UserService $userService
    ) {}

    public function index(Request $request)
    {
        $users = $this->userService->list($request->all());

        return UserResource::collection($users);
    }

    public function show(User $user)
    {
        return new UserResource($user->load('roles'));
    }

    public function store(UserStoreRequest $request)
    {
        $user = $this->userService->create($request->validated());

        return new UserResource($user);
    }

    public function update(UserUpdateRequest $request, User $user)
    {
        $user = $this->userService->update($user, $request->validated());

        return new UserResource($user);
    }

    public function destroy(User $user)
    {
        $this->userService->delete($user);

        return response()->json([
            'message' => 'Usuario eliminado correctamente'
        ]);
    }
}
