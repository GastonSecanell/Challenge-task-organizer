<?php

namespace App\Repositories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

abstract class BaseRepository
{
    public function __construct(
        protected Model $model
    ) {
    }

    public function all(array $relations = [], string $orderBy = 'id', string $direction = 'asc'): Collection
    {
        return $this->model
            ->newQuery()
            ->with($relations)
            ->orderBy($orderBy, $direction)
            ->get();
    }

    public function paginate(
        int $perPage = 10,
        array $relations = [],
        string $orderBy = 'id',
        string $direction = 'desc'
    ): LengthAwarePaginator {
        return $this->model
            ->newQuery()
            ->with($relations)
            ->orderBy($orderBy, $direction)
            ->paginate($perPage);
    }

    public function findById(int $id, array $relations = []): ?Model
    {
        return $this->model
            ->newQuery()
            ->with($relations)
            ->find($id);
    }

    public function findOrFail(int $id, array $relations = []): Model
    {
        return $this->model
            ->newQuery()
            ->with($relations)
            ->findOrFail($id);
    }

    public function create(array $data): Model
    {
        return $this->model->create($data);
    }

    public function update(Model $model, array $data): Model
    {
        $model->update($data);
        return $model;
    }

    public function delete(Model $model): bool
    {
        return (bool) $model->delete();
    }
}
