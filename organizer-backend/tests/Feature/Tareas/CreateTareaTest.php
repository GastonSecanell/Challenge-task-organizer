<?php

namespace Tests\Feature\Tareas;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tarea;
use App\Models\Etiqueta;
use App\Models\Prioridad;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CreateTareaTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_task(): void
    {
        $user = User::factory()->create();

        $prioridad = Prioridad::create([
            'prioridad' => 'ALTA',
        ]);

        $etiqueta = Etiqueta::create([
            'etiqueta' => 'DEV',
        ]);

        Sanctum::actingAs($user);

        $payload = [
            'titulo' => 'Nueva tarea de prueba',
            'descripcion' => '<p>Tarea creada desde test</p>',
            'estado' => 'pendiente',
            'fecha_vencimiento' => now()->addDays(3)->toDateString(),
            'prioridad_id' => $prioridad->id,
            'etiquetas' => [$etiqueta->id],
        ];

        $response = $this->postJson('/api/tareas', $payload);

        $response->assertSuccessful();

        $this->assertDatabaseHas('tareas', [
            'titulo' => 'Nueva tarea de prueba',
            'prioridad_id' => $prioridad->id,
        ]);
    }
}
