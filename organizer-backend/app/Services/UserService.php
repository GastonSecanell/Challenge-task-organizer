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

    public function list(array $filters): array
    {
        $paginator = $this->repository->paginate($filters);

        return [
            'items' => $paginator->items(),
            'filtros' => [
                'por_pagina' => (int) ($filters['por_pagina'] ?? 10),
                'busqueda' => $filters['busqueda'] ?? null,
                'name' => $filters['name'] ?? null,
                'email' => $filters['email'] ?? null,
                'role_id' => $filters['role_id'] ?? null,
                'ordenar_por' => $filters['ordenar_por'] ?? 'id',
                'direccion' => $filters['direccion'] ?? 'desc',
            ],
            'paginacion' => [
                'pagina_actual' => $paginator->currentPage(),
                'por_pagina' => $paginator->perPage(),
                'total' => $paginator->total(),
                'ultima_pagina' => $paginator->lastPage(),
                'desde' => $paginator->firstItem() ?? 0,
                'hasta' => $paginator->lastItem() ?? 0,
            ],
        ];
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
