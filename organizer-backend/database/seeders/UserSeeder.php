<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Administrador General',
                'email' => 'admin@tareas.local.com',
                'password' => Hash::make('administrador'),
            ],
            [
                'name' => 'Usuario Consulta',
                'email' => 'consult@tareas.local.com',
                'password' => Hash::make('consulta'),
            ],
            [
                'name' => 'Usuario Operador',
                'email' => 'operator@tareas.local.com',
                'password' => Hash::make('operador'),
            ],
        ];

        foreach ($users as $data) {
            User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => $data['password'],
                ]
            );
        }
    }
}
