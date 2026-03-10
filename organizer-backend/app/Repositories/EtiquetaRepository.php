<?php

namespace App\Repositories;

use App\Models\Etiqueta;
use Illuminate\Database\Eloquent\Collection;

class EtiquetaRepository extends BaseRepository
{
    public function __construct(Etiqueta $model)
    {
        parent::__construct($model);
    }

    public function listar(): Collection
    {
        return $this->all([], 'id', 'asc');
    }
}
