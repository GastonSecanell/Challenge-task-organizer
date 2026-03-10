<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tarea;
use App\Models\Etiqueta;

class TareaSeeder extends Seeder
{
    public function run(): void
    {
        $tareas = Tarea::factory(10)->create();

        $etiquetas = Etiqueta::pluck('id');

        foreach ($tareas as $tarea) {

            $randomEtiquetas = $etiquetas
                ->random(rand(1,3))
                ->toArray();

            $tarea->etiquetas()->sync($randomEtiquetas);
        }
    }
}
