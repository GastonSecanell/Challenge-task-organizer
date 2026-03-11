<?php

namespace App\Services;

use App\Models\Tarea;
use App\Repositories\TareaRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

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
        return DB::transaction(function () use ($data) {
            $etiquetas = $data['etiquetas'] ?? [];
            unset($data['etiquetas']);

            $tarea = $this->tareaRepository->crear($data);

            $this->tareaRepository->sincronizarEtiquetas($tarea, $etiquetas);

            return $this->tareaRepository->cargarRelaciones($tarea);
        });
    }

    public function obtener(Tarea $tarea): Tarea
    {
        return $this->tareaRepository->cargarRelaciones($tarea);
    }

    public function actualizar(Tarea $tarea, array $data): Tarea
    {
        return DB::transaction(function () use ($tarea, $data) {
            $etiquetas = $data['etiquetas'] ?? [];
            unset($data['etiquetas']);

            $tarea = $this->tareaRepository->actualizar($tarea, $data);
            $this->tareaRepository->sincronizarEtiquetas($tarea, $etiquetas);

            return $this->tareaRepository->cargarRelaciones($tarea);
        });
    }

    public function cambiarEstado(Tarea $tarea, string $estado): Tarea
    {
        return DB::transaction(function () use ($tarea, $estado) {
            $tarea = $this->tareaRepository->actualizar($tarea, [
                'estado' => $estado,
            ]);

            return $this->tareaRepository->cargarRelaciones($tarea);
        });
    }

    public function eliminar(Tarea $tarea): bool
    {
        return DB::transaction(function () use ($tarea) {
            return $this->tareaRepository->eliminar($tarea);
        });
    }

    public function sincronizarEtiquetas(Tarea $tarea, array $etiquetas): Tarea
    {
        return DB::transaction(function () use ($tarea, $etiquetas) {
            $this->tareaRepository->sincronizarEtiquetas($tarea, $etiquetas);
            return $this->tareaRepository->cargarRelaciones($tarea);
        });
    }

    public function cambiarPrioridad(Tarea $tarea, int $prioridadId): Tarea
    {
        return DB::transaction(function () use ($tarea, $prioridadId) {
            $tarea = $this->tareaRepository->actualizar($tarea, [
                'prioridad_id' => $prioridadId,
            ]);

            return $this->tareaRepository->cargarRelaciones($tarea);
        });
    }
}
