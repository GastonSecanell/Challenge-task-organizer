<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $roles = [
            [
                'id' => 1,
                'slug' => 'admin',
                'name' => 'Administrador',
                'description' => 'Control total del sistema',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 2,
                'slug' => 'operador',
                'name' => 'Operador',
                'description' => 'Gestión operativa del sistema',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 3,
                'slug' => 'consulta',
                'name' => 'Consulta',
                'description' => 'Acceso de solo lectura',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 4,
                'slug' => 'auditoria',
                'name' => 'Auditoría',
                'description' => 'Acceso a trazabilidad y auditoría',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('roles')->upsert(
            $roles,
            ['id'],
            ['slug', 'name', 'description', 'updated_at']
        );
    }
}
