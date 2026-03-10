<?php

namespace App\Repositories;

use App\Models\Prioridad;
use Illuminate\Database\Eloquent\Collection;

class PrioridadRepository
{
    public function listar(): Collection
    {
        return Prioridad::query()
            ->orderBy('id')
            ->get();
    }
}
