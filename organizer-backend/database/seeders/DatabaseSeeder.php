<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Board;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            BoardSeeder::class,
        ]);

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@board.local'],
            [
                'name' => 'Administrador',
                'role' => User::ROLE_ADMIN,
                'password' => Hash::make('administrativo'),
            ],
        );

        $op = User::query()->updateOrCreate(
            ['email' => 'op@board.local'],
            [
                'name' => 'Operador',
                'role' => User::ROLE_OPERADOR,
                'password' => Hash::make('operador'),
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'consulta@board.local'],
            [
                'name' => 'Consulta',
                'role' => User::ROLE_CONSULTA,
                'password' => Hash::make('consulta'),
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'auditoria@board.local'],
            [
                'name' => 'Auditoría',
                'role' => User::ROLE_AUDITORIA,
                'password' => Hash::make('auditoria'),
            ],
        );

        // Sincronizar pivote de roles (1 rol principal por usuario)
        User::query()->select(['id', 'role'])->with('roles:id')->get()->each(function (User $u) {
            $roleId = (int) ($u->role ?? User::ROLE_OPERADOR);
            if ($roleId < User::ROLE_ADMIN || $roleId > User::ROLE_AUDITORIA) $roleId = User::ROLE_OPERADOR;
            $u->roles()->sync([$roleId]);
        });

        // Agregar miembros base a todos los boards (admin + operador)
        $boardIds = Board::query()->pluck('id')->all();
        foreach ($boardIds as $boardId) {
            Board::query()->find($boardId)?->members()->syncWithoutDetaching([$admin->id, $op->id]);
        }
    }
}
