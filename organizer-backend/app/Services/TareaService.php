<?php

namespace App\Services;

use App\Models\Tarea;
use App\Repositories\TareaRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TareaService
{
    public function __construct(
        protected TareaRepository $tareaRepository
    ) {
    }

    public function listar(array $filtros = []): LengthAwarePaginator
    {
        return $this->tareaRepository->listar($filtros);
    }

    public function crear(array $data): Tarea
    {
        $etiquetas = $data['etiquetas'] ?? [];
        unset($data['etiquetas']);

        $tarea = $this->tareaRepository->crear($data);

        if (!empty($etiquetas)) {
            $this->tareaRepository->sincronizarEtiquetas($tarea, $etiquetas);
        }

        return $this->tareaRepository->cargarRelaciones($tarea);
    }

    public function obtener(Tarea $tarea): Tarea
    {
        return $this->tareaRepository->cargarRelaciones($tarea);
    }

    public function actualizar(Tarea $tarea, array $data): Tarea
    {
        $etiquetas = $data['etiquetas'] ?? [];
        unset($data['etiquetas']);

        $tarea = $this->tareaRepository->actualizar($tarea, $data);
        $this->tareaRepository->sincronizarEtiquetas($tarea, $etiquetas);

        return $this->tareaRepository->cargarRelaciones($tarea);
    }

    public function cambiarEstado(Tarea $tarea, string $estado): Tarea
    {
        $tarea = $this->tareaRepository->actualizar($tarea, [
            'estado' => $estado,
        ]);

        return $this->tareaRepository->cargarRelaciones($tarea);
    }

    public function eliminar(Tarea $tarea): bool
    {
        return $this->tareaRepository->eliminar($tarea);
    }
}
