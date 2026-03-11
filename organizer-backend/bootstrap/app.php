<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $e, Request $request) {
            if (!$request->expectsJson() && !$request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => 'Los datos enviados no son válidos.',
                'errors' => $e->errors(),
                'codigo' => 422,
            ], 422);
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if (!$request->expectsJson() && !$request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => match ($e->getModel()) {
                    \App\Models\Tarea::class => 'La tarea no fue encontrada.',
                    \App\Models\Prioridad::class => 'La prioridad no fue encontrada.',
                    \App\Models\Etiqueta::class => 'La etiqueta no fue encontrada.',
                    default => 'El recurso solicitado no fue encontrado.',
                },
                'errors' => null,
                'codigo' => 404,
            ], 404);
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (!$request->expectsJson() && !$request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => 'No autenticado.',
                'errors' => null,
                'codigo' => 401,
            ], 401);
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (!$request->expectsJson() && !$request->is('api/*')) {
                return null;
            }

            $status = $e->getStatusCode();

            $mensaje = 'Ocurrió un error en la solicitud.';

            if ($e instanceof NotFoundHttpException) {
                $previous = $e->getPrevious();

                if ($previous instanceof ModelNotFoundException) {
                    $mensaje = match ($previous->getModel()) {
                        \App\Models\Tarea::class => 'La tarea no fue encontrada.',
                        \App\Models\Prioridad::class => 'La prioridad no fue encontrada.',
                        \App\Models\Etiqueta::class => 'La etiqueta no fue encontrada.',
                        default => 'El recurso solicitado no fue encontrado.',
                    };
                } else {
                    $mensaje = 'El recurso solicitado no fue encontrado.';
                }
            } else {
                $mensaje = match ($status) {
                    403 => 'No tiene permisos para realizar esta acción.',
                    405 => 'Método HTTP no permitido.',
                    419 => 'La sesión expiró o el token CSRF es inválido.',
                    429 => 'Demasiadas solicitudes. Intente nuevamente más tarde.',
                    default => $e->getMessage() ?: 'Ocurrió un error en la solicitud.',
                };
            }

            return response()->json([
                'message' => $mensaje,
                'errors' => null,
                'codigo' => $status,
            ], $status);
        });

        $exceptions->render(function (\Throwable $e, Request $request) {
            if (!$request->expectsJson() && !$request->is('api/*')) {
                return null;
            }

            $debug = config('app.debug');

            return response()->json([
                'message' => 'Ocurrió un error interno del servidor.',
                'errors' => $debug ? [
                    'exception' => class_basename($e),
                    'detalle' => $e->getMessage(),
                ] : null,
                'codigo' => 500,
            ], 500);
        });
    })->create();
