<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function __construct(
        private UserRepository $repository
    ) {}

    public function list(array $filters)
    {
        return $this->repository->paginate($filters);
    }

    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $roleId = (int) $data['role_id'];
            unset($data['role_id']);

            $data['password'] = Hash::make($data['password']);

            $user = $this->repository->create($data);
            $this->repository->syncRole($user, $roleId);

            return $this->repository->findById($user->id);
        });
    }

    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $roleId = (int) $data['role_id'];
            unset($data['role_id']);

            if (!empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }

            $updatedUser = $this->repository->update($user, $data);
            $this->repository->syncRole($updatedUser, $roleId);

            return $this->repository->findById($updatedUser->id);
        });
    }

    public function delete(User $user): void
    {
        $this->repository->delete($user);
    }
}
