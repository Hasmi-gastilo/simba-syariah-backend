<?php

namespace Tests\Feature;

use Tests\TestCase;

class AuthApiTest extends TestCase
{
    public function test_user_can_login_with_username_and_password()
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'admin',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Login berhasil.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'user' => [
                        'id',
                        'name',
                        'username',
                        'email',
                        'roles',
                    ],
                ],
            ]);
    }

    public function test_user_cannot_login_with_wrong_password()
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'admin',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
            ]);
    }
}

