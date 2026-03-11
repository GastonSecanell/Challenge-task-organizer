<?php

namespace App\Repositories;

use App\Models\User;

class UserRepository
{
    public function paginate(array $filters)
    {
        $query = User::query()->with('roles');

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('email', 'like', '%' . $filters['search'] . '%');
            });
        }

        if (!empty($filters['name'])) {
            $query->where('name', 'like', '%' . $filters['name'] . '%');
        }

        if (!empty($filters['email'])) {
            $query->where('email', 'like', '%' . $filters['email'] . '%');
        }

        if (!empty($filters['role_id'])) {
            $roleId = (int) $filters['role_id'];

            $query->whereHas('roles', function ($q) use ($roleId) {
                $q->where('roles.id', $roleId);
            });
        }

        return $query->paginate($filters['per_page'] ?? 10);
    }

    public function findById(int $id): ?User
    {
        return User::with('roles')->find($id);
    }

    public function create(array $data): User
    {
        return User::create($data);
    }

    public function update(User $user, array $data): User
    {
        $user->update($data);

        return $user->refresh()->load('roles');
    }

    public function syncRole(User $user, int $roleId): void
    {
        $user->roles()->sync([$roleId]);
    }

    public function delete(User $user): void
    {
        $user->roles()->detach();
        $user->delete();
    }
}
