<?php

namespace Database\Factories;

use App\Models\Tarea;
use Illuminate\Database\Eloquent\Factories\Factory;

class TareaFactory extends Factory
{
    protected $model = Tarea::class;

    public function definition(): array
    {
        $estados = ['pendiente', 'en_progreso', 'completada'];

        $titulos = [
            'Configurar autenticación de usuarios',
            'Corregir errores de validación',
            'Optimizar consulta de listado',
            'Implementar filtros por estado',
            'Diseñar formulario de edición',
            'Revisar integración con frontend',
            'Preparar entorno de pruebas',
            'Actualizar documentación técnica',
        ];

        $descripciones = [
            '<p>Revisar la implementación actual y aplicar mejoras de estabilidad.</p>',
            '<p>Corregir comportamiento de formularios y validar mensajes de error.</p>',
            '<p>Analizar rendimiento del módulo y reducir consultas innecesarias.</p>',
            '<p>Implementar una mejora visual y funcional en la interfaz.</p>',
        ];

        return [
            'titulo' => fake()->randomElement($titulos),
            'descripcion' => fake()->randomElement($descripciones),
            'estado' => fake()->randomElement($estados),
            'fecha_vencimiento' => fake()->optional()->dateTimeBetween('now', '+10 days'),
            'prioridad_id' => fake()->numberBetween(1, 3),
        ];
    }
}
