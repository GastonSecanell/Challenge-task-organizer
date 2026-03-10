<?php

namespace App\Http\Controllers;

use App\Support\ApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\JsonResponse;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    protected function successResponse(
        mixed $data = null,
        string $message = 'Operación realizada correctamente.',
        int $status = 200,
        array $extra = []
    ): JsonResponse {
        return ApiResponse::success($data, $message, $status, $extra);
    }

    protected function errorResponse(
        string $message = 'Ocurrió un error en la solicitud.',
        int $status = 400,
        mixed $errors = null,
        array $extra = []
    ): JsonResponse {
        return ApiResponse::error($message, $status, $errors, $extra);
    }

    protected function paginatedResponse(
        mixed $data,
        string $message = 'Datos obtenidos correctamente.',
        array $paginacion = [],
        array $filtros = [],
        int $status = 200,
        array $extra = []
    ): JsonResponse {
        return ApiResponse::paginated($data, $message, $paginacion, $filtros, $status, $extra);
    }
}
