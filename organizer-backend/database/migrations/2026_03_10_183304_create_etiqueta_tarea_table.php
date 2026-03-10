<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('etiqueta_tarea', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tarea_id')
                ->constrained('tareas')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('etiqueta_id')
                ->constrained('etiquetas')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['tarea_id', 'etiqueta_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('etiqueta_tarea');
    }
};
