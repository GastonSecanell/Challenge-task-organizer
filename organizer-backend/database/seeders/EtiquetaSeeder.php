<?php

namespace Database\Seeders;

use App\Models\Etiqueta;
use Illuminate\Database\Seeder;

class EtiquetaSeeder extends Seeder
{
    public function run(): void
    {
        $etiquetas = ['DEV', 'QA', 'RRHH'];

        foreach ($etiquetas as $etiqueta) {
            Etiqueta::firstOrCreate([
                'etiqueta' => $etiqueta,
            ]);
        }
    }
}
