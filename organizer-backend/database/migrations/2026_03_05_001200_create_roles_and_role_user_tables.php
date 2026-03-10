<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->string('slug', 64)->unique();
            $table->string('name', 120)->unique();
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });

        DB::table('roles')->insert([
            ['id' => 1, 'slug' => 'admin', 'name' => 'Administrador', 'description' => 'Control total del sistema', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'slug' => 'operador', 'name' => 'Operador', 'description' => 'Gestion de tarjetas y trabajo operativo', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'slug' => 'consulta', 'name' => 'Consulta', 'description' => 'Acceso de solo lectura', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'slug' => 'auditoria', 'name' => 'Auditoria', 'description' => 'Acceso a auditoria y trazabilidad', 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::create('role_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('role_id');
            $table->timestamps();

            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->unique('user_id');
            $table->index('role_id');
        });

        $users = DB::table('users')->select(['id', 'role'])->get();
        foreach ($users as $u) {
            $roleId = (int) ($u->role ?? 2);
            if ($roleId < 1 || $roleId > 4) $roleId = 2;

            DB::table('role_user')->insert([
                'user_id' => (int) $u->id,
                'role_id' => $roleId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
    }
};

