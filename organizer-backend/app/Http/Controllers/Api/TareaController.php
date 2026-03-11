<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CambiarEstadoTareaRequest;
use App\Http\Requests\CambiarPrioridadTareaRequest;
use App\Http\Requests\StoreTareaRequest;
use App\Http\Requests\SyncEtiquetasTareaRequest;
use App\Http\Requests\UpdateTareaRequest;
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
            'pagina' => $request->get('pagina', 1),
            'por_pagina' => $request->get('por_pagina', 10),
            'busqueda' => $request->get('busqueda'),
            'estado' => $request->get('estado'),
            'prioridad_id' => $request->get('prioridad_id'),
            'fecha_vencimiento' => $request->get('fecha_vencimiento'),
            'ordenar_por' => $request->get('ordenar_por', 'id'),
            'direccion' => $request->get('direccion', 'desc'),
        ];

        $tareas = $this->tareaService->listar($filtros);

        return $this->paginatedResponse(
            TareaResource::collection($tareas->items()),
            $tareas->total() > 0
                ? 'Tareas obtenidas correctamente.'
                : 'No se encontraron tareas.',
            [
                'pagina_actual' => $tareas->currentPage(),
                'por_pagina' => $tareas->perPage(),
                'total' => $tareas->total(),
                'ultima_pagina' => $tareas->lastPage(),
                'desde' => $tareas->firstItem(),
                'hasta' => $tareas->lastItem(),
            ],
            $filtros
        );
    }
    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTareaRequest $request): JsonResponse
    {
        $tarea = $this->tareaService->crear($request->validated());

        return $this->successResponse(
            new TareaResource($tarea),
            'Tarea creada correctamente.',
            201
        );
    }
    /**
     * Display the specified resource.
     */

    public function show(Tarea $tarea): JsonResponse
    {
        $tarea = $this->tareaService->obtener($tarea);

        return $this->successResponse(
            new TareaResource($tarea),
            'Tarea obtenida correctamente.'
        );
    }
    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTareaRequest $request, Tarea $tarea): JsonResponse
    {
        $tarea = $this->tareaService->actualizar($tarea, $request->validated());

        return $this->successResponse(
            new TareaResource($tarea),
            'Tarea actualizada correctamente.'
        );
    }
    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Tarea $tarea): JsonResponse
    {
        $this->tareaService->eliminar($tarea);

        return $this->successResponse(
            null,
            'Tarea eliminada correctamente.'
        );
    }

    public function cambiarEstado(CambiarEstadoTareaRequest $request, Tarea $tarea): JsonResponse
    {
        $tarea = $this->tareaService->cambiarEstado($tarea, $request->validated()['estado']);

        return response()->json([
            'message' => 'Estado de la tarea actualizado correctamente.',
            'data' => new TareaResource($tarea),
        ]);
    }

    public function cambiarPrioridad(CambiarPrioridadTareaRequest $request, Tarea $tarea): JsonResponse
    {
        $tarea = $this->tareaService->cambiarPrioridad(
            $tarea,
            (int) $request->validated()['prioridad_id']
        );

        return $this->successResponse(
            new TareaResource($tarea),
            'Prioridad de la tarea actualizada correctamente.'
        );
    }

    public function syncEtiquetas(SyncEtiquetasTareaRequest $request, Tarea $tarea): JsonResponse
    {
        $tarea = $this->tareaService->sincronizarEtiquetas(
            $tarea,
            $request->validated()['etiquetas'] ?? []
        );

        return $this->successResponse(
            new TareaResource($tarea),
            'Etiquetas actualizadas correctamente.'
        );
    }

}
