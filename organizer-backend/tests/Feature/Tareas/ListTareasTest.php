<?php

namespace Tests\Feature\Tareas;

use App\Models\Prioridad;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ListTareasTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_autenticado_puede_listar_tareas(): void
    {
        $user = User::factory()->create();
        $prioridad = Prioridad::factory()->create();

        Tarea::factory()->count(5)->create([
            'prioridad_id' => $prioridad->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/tareas');

        $response
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
            ]);

        $this->assertIsArray($response->json('data'));
        $this->assertCount(5, $response->json('data'));
    }

    public function test_usuario_no_autenticado_no_puede_listar_tareas(): void
    {
        $response = $this->getJson('/api/tareas');

        $response->assertStatus(401);
    }

    public function test_listado_puede_filtrar_por_estado_si_el_endpoint_lo_soporta(): void
    {
        $user = User::factory()->create();
        $prioridad = Prioridad::factory()->create();

        Tarea::factory()->create([
            'titulo' => 'Tarea pendiente',
            'estado' => 'pendiente',
            'prioridad_id' => $prioridad->id,
        ]);

        Tarea::factory()->create([
            'titulo' => 'Tarea completada',
            'estado' => 'completada',
            'prioridad_id' => $prioridad->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/tareas?estado=pendiente');

        $response->assertStatus(200);

        $data = $response->json('data');

        $this->assertNotEmpty($data);
        $this->assertEquals('pendiente', $data[0]['estado']);
    }
}
