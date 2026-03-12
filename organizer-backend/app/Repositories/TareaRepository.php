<?php

namespace App\Repositories;

use App\Models\Tarea;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TareaRepository extends BaseRepository
{
    public function __construct(Tarea $model)
    {
        parent::__construct($model);
    }

    public function listar(array $filtros = []): LengthAwarePaginator
    {
        $pagina = (int) ($filtros['pagina'] ?? 1);
        $porPagina = (int) ($filtros['por_pagina'] ?? 10);
        $porPagina = $porPagina > 0 ? min($porPagina, 100) : 10;
        $ordenarPorPermitidos = ['id', 'titulo', 'estado', 'fecha_vencimiento', 'created_at'];
        $ordenarPor = $filtros['ordenar_por'] ?? 'id';
        $direccion = strtolower($filtros['direccion'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if (!in_array($ordenarPor, $ordenarPorPermitidos, true)) {
            $ordenarPor = 'id';
        }

        return $this->model
            ->newQuery()
            ->with(['prioridad', 'etiquetas'])
            ->busqueda($filtros['busqueda'] ?? null)
            ->porEstado($filtros['estado'] ?? null)
            ->porEtiqueta(isset($filtros['etiqueta_id']) && $filtros['etiqueta_id'] !== ''
                ? (int) $filtros['etiqueta_id']
                : null)
            ->porPrioridad(isset($filtros['prioridad_id']) && $filtros['prioridad_id'] !== ''
                ? (int) $filtros['prioridad_id']
                : null)
            ->porFechaVencimiento($filtros['fecha_vencimiento'] ?? null)
            ->orderBy($ordenarPor, $direccion)
            ->paginate($porPagina, ['*'], 'page', $pagina)
            ->appends($filtros);
    }

    public function crear(array $data): Tarea
    {
        return $this->create($data);
    }

    public function actualizar(Tarea $tarea, array $data): Tarea
    {
        /** @var Tarea $tarea */
        return $this->update($tarea, $data);
    }

    public function eliminar(Tarea $tarea): bool
    {
        return $this->delete($tarea);
    }

    public function sincronizarEtiquetas(Tarea $tarea, array $etiquetas): void
    {
        $tarea->etiquetas()->sync($etiquetas);
    }

    public function cargarRelaciones(Tarea $tarea): Tarea
    {
        return $tarea->load(['prioridad', 'etiquetas']);
    }
}
