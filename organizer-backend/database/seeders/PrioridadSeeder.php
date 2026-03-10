<?php

namespace Database\Seeders;

use App\Models\Prioridad;
use Illuminate\Database\Seeder;

class PrioridadSeeder extends Seeder
{
    public function run(): void
    {
        $prioridades = ['BAJA', 'MEDIA', 'ALTA'];

        foreach ($prioridades as $prioridad) {
            Prioridad::firstOrCreate([
                'prioridad' => $prioridad,
            ]);
        }
    }
}
