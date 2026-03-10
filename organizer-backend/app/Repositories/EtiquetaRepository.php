<?php

namespace App\Repositories;

use App\Models\Etiqueta;
use Illuminate\Database\Eloquent\Collection;

class EtiquetaRepository
{
    public function listar(): Collection
    {
        return Etiqueta::query()
            ->orderBy('id')
            ->get();
    }
}
