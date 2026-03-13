<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_puede_iniciar_sesion_con_credenciales_validas(): void
    {
        $password = 'password123';

        $user = User::factory()->create([
            'email' => 'admin@test.com',
            'password' => bcrypt($password),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => $password,
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'user',
                ],
            ]);

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertEquals($user->id, $response->json('data.user.id'));
    }

    public function test_usuario_no_puede_iniciar_sesion_con_credenciales_invalidas(): void
    {
        User::factory()->create([
            'email' => 'admin@test.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'admin@test.com',
            'password' => 'incorrecta',
        ]);

        $response
            ->assertStatus(401)
            ->assertJsonStructure([
                'success',
                'message',
            ]);
    }

    public function test_login_valida_campos_requeridos(): void
    {
        $response = $this->postJson('/api/login', []);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'email',
                'password',
            ]);
    }
}
