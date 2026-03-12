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
        $tareasFactory = Tarea::factory(15)->create();

        $idsEtiquetas = Etiqueta::pluck('id');

        foreach ($tareasFactory as $tarea) {
            $randomEtiquetas = $idsEtiquetas
                ->random(rand(1, 3))
                ->toArray();

            $tarea->etiquetas()->sync($randomEtiquetas);
        }

        /**
         * 2) Tareas manuales pensadas para demo
         */
        $tareas = [
            [
                'titulo' => 'Implementar login con JWT',
                'descripcion' => '<p>Desarrollar el flujo de autenticación de la API utilizando <strong>JWT</strong>.</p><ul><li>Crear endpoint de login</li><li>Validar credenciales</li><li>Retornar token</li></ul>',
                'estado' => 'en_progreso',
                'fecha_vencimiento' => now()->addDay()->toDateString(),
                'prioridad_id' => $prioridades['ALTA'],
                'etiquetas' => ['DEV'],
            ],
            [
                'titulo' => 'Corregir validaciones del formulario de tareas',
                'descripcion' => '<p>Revisar validaciones en frontend y backend para evitar registros incompletos.</p><p>Controlar título, prioridad y formato de fecha.</p>',
                'estado' => 'pendiente',
                'fecha_vencimiento' => now()->toDateString(),
                'prioridad_id' => $prioridades['ALTA'],
                'etiquetas' => ['DEV', 'QA'],
            ],
            [
                'titulo' => 'Probar flujo completo del CRUD',
                'descripcion' => '<p>Ejecutar pruebas funcionales sobre el módulo de tareas.</p><ul><li>Alta</li><li>Edición</li><li>Eliminación</li><li>Cambio de estado</li></ul>',
                'estado' => 'pendiente',
                'fecha_vencimiento' => now()->addDays(2)->toDateString(),
                'prioridad_id' => $prioridades['MEDIA'],
                'etiquetas' => ['QA'],
            ],
            [
                'titulo' => 'Diseñar vista responsive del dashboard',
                'descripcion' => '<p>Adaptar la pantalla principal para dispositivos móviles y tablets.</p><p>Revisar cards, espaciados y estructura general.</p>',
                'estado' => 'en_progreso',
                'fecha_vencimiento' => now()->addDays(4)->toDateString(),
                'prioridad_id' => $prioridades['MEDIA'],
                'etiquetas' => ['DEV'],
            ],
            [
                'titulo' => 'Documentar endpoints del módulo',
                'descripcion' => '<p>Generar documentación breve de la API REST.</p><ul><li>Listar tareas</li><li>Crear tarea</li><li>Editar tarea</li><li>Eliminar tarea</li></ul>',
                'estado' => 'completada',
                'fecha_vencimiento' => now()->subDay()->toDateString(),
                'prioridad_id' => $prioridades['BAJA'],
                'etiquetas' => ['DEV'],
            ],
            [
                'titulo' => 'Relevar necesidades del área RRHH',
                'descripcion' => '<p>Analizar si las etiquetas y prioridades actuales cubren correctamente los requerimientos del área.</p>',
                'estado' => 'pendiente',
                'fecha_vencimiento' => now()->addDays(5)->toDateString(),
                'prioridad_id' => $prioridades['MEDIA'],
                'etiquetas' => ['RRHH'],
            ],
            [
                'titulo' => 'Ajustar estilos de badges de estado',
                'descripcion' => '<p>Mejorar contraste visual y legibilidad de los badges de estado.</p><p>Validar su visualización en modo claro y oscuro.</p>',
                'estado' => 'completada',
                'fecha_vencimiento' => now()->subDays(3)->toDateString(),
                'prioridad_id' => $prioridades['BAJA'],
                'etiquetas' => ['DEV'],
            ],
            [
                'titulo' => 'Validar filtros por fecha de vencimiento',
                'descripcion' => '<p>Probar filtros por fecha desde la interfaz y verificar que el backend responda correctamente.</p>',
                'estado' => 'en_progreso',
                'fecha_vencimiento' => now()->addDays(3)->toDateString(),
                'prioridad_id' => $prioridades['MEDIA'],
                'etiquetas' => ['QA'],
            ],
            [
                'titulo' => 'Preparar datos iniciales para la demo',
                'descripcion' => '<p>Cargar prioridades, etiquetas, usuarios y tareas de ejemplo para la presentación técnica.</p><p>Verificar que el seeder sea <strong>reproducible</strong>.</p>',
                'estado' => 'completada',
                'fecha_vencimiento' => now()->toDateString(),
                'prioridad_id' => $prioridades['ALTA'],
                'etiquetas' => ['DEV', 'QA'],
            ],
            [
                'titulo' => 'Refactorizar store global de tareas',
                'descripcion' => '<p>Separar responsabilidades del store para mejorar mantenimiento.</p><ul><li>Estado</li><li>Loading</li><li>Manejo de errores</li></ul>',
                'estado' => 'pendiente',
                'fecha_vencimiento' => now()->addWeek()->toDateString(),
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
