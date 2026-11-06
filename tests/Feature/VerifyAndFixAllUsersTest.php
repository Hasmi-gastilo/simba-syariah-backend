<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class VerifyAndFixAllUsersTest extends TestCase
{
    public function test_ensure_all_users_have_proper_passwords_and_roles(): void
    {
        // 1. Expected default users and roles
        $defaultUsers = [
            'admin' => ['name' => 'Super Administrator', 'email' => 'admin@simba.ac.id', 'role' => 'super_admin'],
            'dosen' => ['name' => 'Dosen Pembimbing, M.E.I', 'email' => 'dosen@simba.ac.id', 'role' => 'dosen'],
            'spv1' => ['name' => 'Supervisor Operasional', 'email' => 'spv1@simba.ac.id', 'role' => 'supervisor'],
            'ao1' => ['name' => 'Account Officer 1', 'email' => 'ao1@simba.ac.id', 'role' => 'account_officer'],
            'cs1' => ['name' => 'Customer Service 1', 'email' => 'cs1@simba.ac.id', 'role' => 'customer_service'],
            'teller1' => ['name' => 'Teller Kas 1', 'email' => 'teller1@simba.ac.id', 'role' => 'teller'],
            'mahasiswa1' => ['name' => 'Mahasiswa Praktikan', 'email' => 'mahasiswa1@simba.ac.id', 'role' => 'mahasiswa'],
        ];

        foreach ($defaultUsers as $username => $data) {
            $user = User::firstOrCreate(
                ['username' => $username],
                [
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => Hash::make('Exyakuza28!?'),
                    'status' => 'active',
                ]
            );

            // Always ensure password is set to Exyakuza28!?
            $user->update([
                'password' => Hash::make('Exyakuza28!?'),
                'status' => 'active',
            ]);

            // Ensure role is assigned
            $role = Role::where('slug', $data['role'])->first();
            if ($role) {
                // Ensure the user has their primary role
                if (!$user->roles()->where('slug', $data['role'])->exists()) {
                    $user->roles()->attach($role->id);
                }
            }
        }

        // 2. Test login via API endpoint for EACH user using BOTH username and email, and password 'Exyakuza28!?'
        foreach ($defaultUsers as $username => $data) {
            // Login with username
            $response = $this->postJson('/api/v1/auth/login', [
                'login' => $username,
                'password' => 'Exyakuza28!?',
            ]);
            $response->assertStatus(200);
            $response->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'username' => $username,
                    ],
                ],
            ]);
            $this->assertNotEmpty($response->json('data.token'));

            // Login with email
            $responseEmail = $this->postJson('/api/v1/auth/login', [
                'login' => $data['email'],
                'password' => 'Exyakuza28!?',
            ]);
            $responseEmail->assertStatus(200);
            $responseEmail->assertJson([
                'success' => true,
            ]);
        }
    }
}

