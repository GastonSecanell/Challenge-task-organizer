<?php

namespace App\Repositories;

use App\Models\User;

class UserRepository
{
    public function paginate(array $filters)
    {
        $query = User::query()->with('roles');

        if (!empty($filters['busqueda'])) {
            $busqueda = $filters['busqueda'];

            $query->where(function ($q) use ($busqueda) {
                $q->where('name', 'like', "%{$busqueda}%")
                  ->orWhere('email', 'like', "%{$busqueda}%");
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

        $ordenarPor = $filters['ordenar_por'] ?? 'id';
        $direccion = $filters['direccion'] ?? 'desc';
        $porPagina = (int) ($filters['por_pagina'] ?? 10);
        $pagina = (int) ($filters['pagina'] ?? 1);

        $columnasPermitidas = ['id', 'name', 'email', 'created_at', 'updated_at'];
        if (!in_array($ordenarPor, $columnasPermitidas, true)) {
            $ordenarPor = 'id';
        }

        $direccion = strtolower($direccion) === 'asc' ? 'asc' : 'desc';

        return $query
            ->orderBy($ordenarPor, $direccion)
            ->paginate($porPagina, ['*'], 'page', $pagina);
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
