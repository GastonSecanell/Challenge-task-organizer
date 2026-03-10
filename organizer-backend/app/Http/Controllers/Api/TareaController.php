<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTareaRequest;
use App\Http\Requests\UpdateTareaRequest;
use App\Http\Requests\CambiarEstadoTareaRequest;
use App\Http\Resources\TareaResource;
use App\Models\Tarea;
use App\Services\TareaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TareaController extends Controller
{
    public function __construct(
        protected TareaService $tareaService
    ) {
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $filtros = [
            'por_pagina' => $request->get('por_pagina', 10),
            'busqueda' => $request->get('busqueda'),
            'estado' => $request->get('estado'),
            'prioridad_id' => $request->get('prioridad_id'),
            'fecha_vencimiento' => $request->get('fecha_vencimiento'),
            'ordenar_por' => $request->get('ordenar_por', 'id'),
            'direccion' => $request->get('direccion', 'desc'),
        ];

        $tareas = $this->tareaService->listar($filtros);

        return response()->json([
            'message' => $tareas->total() > 0
                ? 'Tareas obtenidas correctamente.'
                : 'No se encontraron tareas.',
            'data' => TareaResource::collection($tareas->items()),
            'paginacion' => [
                'pagina_actual' => $tareas->currentPage(),
                'por_pagina' => $tareas->perPage(),
                'total' => $tareas->total(),
                'ultima_pagina' => $tareas->lastPage(),
                'desde' => $tareas->firstItem(),
                'hasta' => $tareas->lastItem(),
            ],
            'filtros' => $filtros,
        ]);
    }
    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTareaRequest $request): JsonResponse
    {
        $tarea = $this->tareaService->crear($request->validated());

        return response()->json([
            'message' => 'Tarea creada correctamente.',
            'data' => new TareaResource($tarea),
        ], 201);
    }
    /**
     * Display the specified resource.
     */

    public function show(Tarea $tarea): JsonResponse
    {
        $tarea = $this->tareaService->obtener($tarea);

        return response()->json([
            'message' => 'Tarea obtenida correctamente.',
            'data' => new TareaResource($tarea),
        ]);
    }
    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTareaRequest $request, Tarea $tarea): JsonResponse
    {
        $tarea = $this->tareaService->actualizar($tarea, $request->validated());

        return response()->json([
            'message' => 'Tarea actualizada correctamente.',
            'data' => new TareaResource($tarea),
        ]);
    }
    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Tarea $tarea): JsonResponse
    {
        $this->tareaService->eliminar($tarea);

        return response()->json([
            'message' => 'Tarea eliminada correctamente.',
            'data' => null,
        ]);
    }

    public function cambiarEstado(CambiarEstadoTareaRequest $request, Tarea $tarea): JsonResponse
    {
        $tarea = $this->tareaService->cambiarEstado($tarea, $request->validated()['estado']);

        return response()->json([
            'message' => 'Estado de la tarea actualizado correctamente.',
            'data' => new TareaResource($tarea),
        ]);
    }
}
