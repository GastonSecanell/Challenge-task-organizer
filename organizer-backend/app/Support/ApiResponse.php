<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function success(
        mixed $data = null,
        string $message = 'Operación realizada correctamente.',
        int $status = 200,
        array $extra = []
    ): JsonResponse {
        return response()->json(array_merge([
            'message' => $message,
            'data' => $data,
        ], $extra), $status);
    }

    public static function error(
        string $message = 'Ocurrió un error en la solicitud.',
        int $status = 400,
        mixed $errors = null,
        array $extra = []
    ): JsonResponse {
        return response()->json(array_merge([
            'message' => $message,
            'errors' => $errors,
            'status' => $status,
        ], $extra), $status);
    }

    public static function paginated(
        mixed $data,
        string $message = 'Datos obtenidos correctamente.',
        array $paginacion = [],
        array $filtros = [],
        int $status = 200,
        array $extra = []
    ): JsonResponse {
        return response()->json(array_merge([
            'message' => $message,
            'data' => $data,
            'paginacion' => $paginacion,
            'filtros' => $filtros,
        ], $extra), $status);
    }
}
