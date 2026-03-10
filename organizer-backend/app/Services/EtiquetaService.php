<?php

namespace App\Services;

use App\Repositories\EtiquetaRepository;
use Illuminate\Database\Eloquent\Collection;

class EtiquetaService
{
    public function __construct(
        protected EtiquetaRepository $etiquetaRepository
    ) {
    }

    public function listar(): Collection
    {
        return $this->etiquetaRepository->listar();
    }
}
