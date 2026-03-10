<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PrioridadResource;
use App\Services\PrioridadService;
use Illuminate\Http\JsonResponse;

class PrioridadController extends Controller
{
    public function __construct(
        protected PrioridadService $prioridadService
    ) {
    }
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $prioridades = $this->prioridadService->listar();

        return $this->successResponse(
            PrioridadResource::collection($prioridades),
            $prioridades->isNotEmpty()
                ? 'Prioridades obtenidas correctamente.'
                : 'No se encontraron prioridades.'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Prioridad $prioridad)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Prioridad $prioridad)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Prioridad $prioridad)
    {
        //
    }
}
