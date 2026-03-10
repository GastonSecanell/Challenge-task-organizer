<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleUserSeeder extends Seeder
{
    public function run(): void
    {
        $map = [
            'admin@tareas.local.com' => 1,
            'operator@tareas.local.com' => 2,
            'consult@tareas.local.com' => 3,
        ];

        foreach ($map as $email => $roleId) {
            $user = User::where('email', $email)->first();

            if (!$user) {
                continue;
            }

            DB::table('role_user')->updateOrInsert(
                ['user_id' => $user->id],
                [
                    'role_id' => $roleId,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
