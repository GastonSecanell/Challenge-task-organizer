<?php

namespace Database\Seeders;

use App\Models\Etiqueta;
use App\Models\Prioridad;
use App\Models\Tarea;
use Illuminate\Database\Seeder;

class TareaSeeder extends Seeder
{
    public function run(): void
    {
        $prioridades = Prioridad::pluck('id', 'prioridad');
        $etiquetas = Etiqueta::pluck('id', 'etiqueta');

        /**
         * 1) Tareas generadas con factory
         */
        $tareasFactory = Tarea::factory(5)->create();

        $idsEtiquetas = Etiqueta::pluck('id');

        foreach ($tareasFactory as $tarea) {
            $randomEtiquetas = $idsEtiquetas
                ->random(rand(1, 3))
                ->toArray();

            $tarea->etiquetas()->sync($randomEtiquetas);
        }

        /**
         * 2) Tareas manuales más realistas para la demo
         */
        $tareas = [
            [
                'titulo' => 'Implementar login con JWT',
                'descripcion' => '<p>Desarrollar el flujo de autenticación para la API utilizando <strong>JWT</strong>.</p><ul><li>Crear endpoint de login</li><li>Validar credenciales</li><li>Retornar token</li></ul>',
                'estado' => 'en_progreso',
                'fecha_vencimiento' => now()->addDays(2)->toDateString(),
                'prioridad_id' => $prioridades['ALTA'],
                'etiquetas' => ['DEV'],
            ],
            [
                'titulo' => 'Corregir validaciones del formulario de tareas',
                'descripcion' => '<p>Revisar validaciones en frontend y backend para evitar registros incompletos.</p><p>Validar <em>título</em>, <em>prioridad</em> y formato de fecha.</p>',
                'estado' => 'pendiente',
                'fecha_vencimiento' => now()->addDays(1)->toDateString(),
                'prioridad_id' => $prioridades['MEDIA'],
                'etiquetas' => ['DEV', 'QA'],
            ],
            [
                'titulo' => 'Probar flujo completo de creación de tareas',
                'descripcion' => '<p>Ejecutar pruebas funcionales del CRUD de tareas.</p><ul><li>Alta</li><li>Edición</li><li>Eliminación</li><li>Cambio de estado</li></ul>',
                'estado' => 'pendiente',
                'fecha_vencimiento' => now()->addDays(3)->toDateString(),
                'prioridad_id' => $prioridades['ALTA'],
                'etiquetas' => ['QA'],
            ],
            [
                'titulo' => 'Diseñar vista responsive del dashboard',
                'descripcion' => '<p>Adaptar la pantalla principal para dispositivos móviles y tablets.</p><p>Revisar grillas, cards y spacing.</p>',
                'estado' => 'en_progreso',
                'fecha_vencimiento' => now()->addDays(4)->toDateString(),
                'prioridad_id' => $prioridades['MEDIA'],
                'etiquetas' => ['DEV'],
            ],
            [
                'titulo' => 'Documentar endpoints del módulo de tareas',
                'descripcion' => '<p>Generar documentación básica de la API REST.</p><ul><li>Listar tareas</li><li>Crear tarea</li><li>Editar tarea</li><li>Eliminar tarea</li></ul>',
                'estado' => 'completada',
                'fecha_vencimiento' => now()->subDay()->toDateString(),
                'prioridad_id' => $prioridades['BAJA'],
                'etiquetas' => ['DEV'],
            ],
            [
                'titulo' => 'Revisar casos de uso con el área de RRHH',
                'descripcion' => '<p>Validar si las etiquetas y prioridades definidas cubren las necesidades del sector.</p><p>Registrar observaciones para próximos cambios.</p>',
                'estado' => 'pendiente',
                'fecha_vencimiento' => now()->addDays(5)->toDateString(),
                'prioridad_id' => $prioridades['MEDIA'],
                'etiquetas' => ['RRHH'],
            ],
            [
                'titulo' => 'Ajustar estilos de badges de estado',
                'descripcion' => '<p>Mejorar contraste visual de los estados <strong>pendiente</strong>, <strong>en progreso</strong> y <strong>completada</strong>.</p>',
                'estado' => 'completada',
                'fecha_vencimiento' => now()->subDays(2)->toDateString(),
                'prioridad_id' => $prioridades['BAJA'],
                'etiquetas' => ['DEV'],
            ],
            [
                'titulo' => 'Validar filtros por fecha de vencimiento',
                'descripcion' => '<p>Probar filtros por fecha desde la interfaz y verificar respuesta correcta del backend.</p>',
                'estado' => 'en_progreso',
                'fecha_vencimiento' => now()->addDays(2)->toDateString(),
                'prioridad_id' => $prioridades['MEDIA'],
                'etiquetas' => ['QA'],
            ],
            [
                'titulo' => 'Preparar datos iniciales para demo técnica',
                'descripcion' => '<p>Cargar prioridades, etiquetas, usuarios y tareas de ejemplo para la presentación.</p><p>Verificar que el seeder sea <strong>reproducible</strong>.</p>',
                'estado' => 'completada',
                'fecha_vencimiento' => now()->toDateString(),
                'prioridad_id' => $prioridades['ALTA'],
                'etiquetas' => ['DEV', 'QA'],
            ],
            [
                'titulo' => 'Refactorizar store global de tareas',
                'descripcion' => '<p>Separar acciones, estado y manejo de errores para mejorar mantenimiento.</p><ul><li>loading</li><li>errores</li><li>sincronización de lista</li></ul>',
                'estado' => 'pendiente',
                'fecha_vencimiento' => now()->addDays(6)->toDateString(),
                'prioridad_id' => $prioridades['ALTA'],
                'etiquetas' => ['DEV'],
            ],
        ];

        foreach ($tareas as $data) {
            $etiquetasTarea = $data['etiquetas'];
            unset($data['etiquetas']);

            $tarea = Tarea::create($data);

            $ids = collect($etiquetasTarea)
                ->map(fn ($etiqueta) => $etiquetas[$etiqueta] ?? null)
                ->filter()
                ->values()
                ->all();

            $tarea->etiquetas()->sync($ids);
        }
    }
}
