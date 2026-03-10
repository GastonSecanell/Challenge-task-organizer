<?php

namespace App\Repositories;

use App\Models\Prioridad;
use Illuminate\Database\Eloquent\Collection;

class PrioridadRepository extends BaseRepository
{
    public function __construct(Prioridad $model)
    {
        parent::__construct($model);
    }

    public function listar(): Collection
    {
        return $this->all([], 'id', 'asc');
    }
}
