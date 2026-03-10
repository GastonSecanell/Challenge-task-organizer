<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EtiquetaResource;
use App\Services\EtiquetaService;
use Illuminate\Http\JsonResponse;

class EtiquetaController extends Controller
{
    public function __construct(
        protected EtiquetaService $etiquetaService
    ) {
    }
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $etiquetas = $this->etiquetaService->listar();

        return response()->json([
            'message' => $etiquetas->isNotEmpty()
                ? 'Etiquetas obtenidas correctamente.'
                : 'No se encontraron etiquetas.',
            'data' => EtiquetaResource::collection($etiquetas),
        ]);
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
    public function show(Etiqueta $etiqueta)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Etiqueta $etiqueta)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Etiqueta $etiqueta)
    {
        //
    }
}
