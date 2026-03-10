<?php

namespace App\Repositories;

use App\Models\Tarea;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TareaRepository
{
    public function listar(array $filtros = []): LengthAwarePaginator
    {
        $porPagina = (int) ($filtros['por_pagina'] ?? 10);
        $porPagina = $porPagina > 0 ? min($porPagina, 100) : 10;

        $ordenarPorPermitidos = ['id', 'titulo', 'estado', 'fecha_vencimiento', 'created_at'];
        $ordenarPor = $filtros['ordenar_por'] ?? 'id';
        $direccion = strtolower($filtros['direccion'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if (!in_array($ordenarPor, $ordenarPorPermitidos, true)) {
            $ordenarPor = 'id';
        }

        return Tarea::query()
            ->with(['prioridad', 'etiquetas'])
            ->busqueda($filtros['busqueda'] ?? null)
            ->porEstado($filtros['estado'] ?? null)
            ->porPrioridad(isset($filtros['prioridad_id']) && $filtros['prioridad_id'] !== ''
                ? (int) $filtros['prioridad_id']
                : null)
            ->porFechaVencimiento($filtros['fecha_vencimiento'] ?? null)
            ->orderBy($ordenarPor, $direccion)
            ->paginate($porPagina)
            ->appends($filtros);
    }

    public function crear(array $data): Tarea
    {
        return Tarea::create($data);
    }

    public function actualizar(Tarea $tarea, array $data): Tarea
    {
        $tarea->update($data);
        return $tarea;
    }

    public function eliminar(Tarea $tarea): bool
    {
        return (bool) $tarea->delete();
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
