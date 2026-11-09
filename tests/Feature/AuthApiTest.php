<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_mengembalikan_token(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])
            ->assertCreated()
            ->assertJsonStructure(['message', 'token', 'token_type', 'user' => ['id', 'name', 'email']]);

        $this->assertDatabaseHas('users', ['email' => 'budi@example.com']);
    }

    public function test_login_berhasil_dan_gagal(): void
    {
        $user = User::factory()->create(['password' => 'password123']);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password123'])
            ->assertOk()
            ->assertJsonStructure(['message', 'token', 'token_type', 'user' => ['id', 'email']]);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'salah-banget'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_me_butuh_token_dan_logout_menghapus_token(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();

        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);

        $this->withToken($token)->postJson('/api/logout')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
