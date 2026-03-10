<?php

namespace App\Services;

use App\Repositories\PrioridadRepository;
use Illuminate\Database\Eloquent\Collection;

class PrioridadService
{
    public function __construct(
        protected PrioridadRepository $prioridadRepository
    ) {
    }

    public function listar(): Collection
    {
        return $this->prioridadRepository->listar();
    }
}
