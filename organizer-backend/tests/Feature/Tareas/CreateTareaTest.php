<?php

namespace Tests\Feature\Tareas;

use App\Models\Etiqueta;
use App\Models\Prioridad;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CreateTareaTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_autenticado_puede_crear_una_tarea(): void
    {
        $user = User::factory()->create();
        $prioridad = Prioridad::factory()->create();
        $etiquetas = Etiqueta::factory()->count(2)->create();

        Sanctum::actingAs($user);

        $payload = [
            'titulo' => 'Crear documentación del challenge',
            'descripcion' => 'Hay que dejar README, tests y docker listos.',
            'estado' => 'pendiente',
            'fecha_vencimiento' => now()->addDays(5)->format('Y-m-d'),
            'prioridad_id' => $prioridad->id,
            'etiquetas' => $etiquetas->pluck('id')->toArray(),
        ];

        $response = $this->postJson('/api/tareas', $payload);

        $response
            ->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'titulo',
                    'descripcion',
                    'estado',
                    'fecha_vencimiento',
                    'prioridad',
                    'etiquetas',
                ],
            ]);

        $this->assertDatabaseHas('tareas', [
            'titulo' => 'Crear documentación del challenge',
            'descripcion' => 'Hay que dejar README, tests y docker listos.',
            'estado' => 'pendiente',
            'prioridad_id' => $prioridad->id,
        ]);

        $tarea = Tarea::first();

        $this->assertNotNull($tarea);
        $this->assertCount(2, $tarea->etiquetas);
    }

    public function test_usuario_no_autenticado_no_puede_crear_una_tarea(): void
    {
        $prioridad = Prioridad::factory()->create();

        $response = $this->postJson('/api/tareas', [
            'titulo' => 'Tarea sin auth',
            'descripcion' => 'No debería crearse',
            'estado' => 'pendiente',
            'prioridad_id' => $prioridad->id,
        ]);

        $response->assertStatus(401);
    }

    public function test_creacion_de_tarea_valida_los_campos_obligatorios(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/tareas', []);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'titulo',
                'descripcion',
                'estado',
                'prioridad_id',
            ]);
    }

    public function test_no_permite_crear_tarea_con_estado_invalido(): void
    {
        $user = User::factory()->create();
        $prioridad = Prioridad::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/tareas', [
            'titulo' => 'Tarea inválida',
            'descripcion' => 'Estado inválido',
            'estado' => 'cerrada',
            'prioridad_id' => $prioridad->id,
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['estado']);
    }
}
