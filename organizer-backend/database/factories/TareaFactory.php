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

        return [
            'titulo' => $this->faker->sentence(4),
            'descripcion' => $this->faker->paragraph(),
            'estado' => $this->faker->randomElement($estados),
            'fecha_vencimiento' => $this->faker->optional()->dateTimeBetween('now', '+10 days'),
            'prioridad_id' => $this->faker->numberBetween(1, 3),
        ];
    }
}
