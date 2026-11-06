<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\AcademicClass;
use App\Models\Student;
use App\Models\Role;
use App\Models\PracticumSession;

class AcademicApiTest extends TestCase
{
    private function authenticateUser(string $roleSlug = 'dosen'): User
    {
        $user = User::whereHas('roles', function ($q) use ($roleSlug) {
            $q->where('slug', $roleSlug);
        })->first();

        if (!$user) {
            $user = User::first();
        }

        return $user;
    }

    public function test_can_list_classes(): void
    {
        $user = $this->authenticateUser('dosen');

        $response = $this->actingAs($user)
            ->getJson('/api/v1/academic/classes');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertNotEmpty($response->json('data'));
    }

    public function test_can_list_students(): void
    {
        $user = $this->authenticateUser('dosen');

        $response = $this->actingAs($user)
            ->getJson('/api/v1/academic/students');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertNotEmpty($response->json('data'));
    }

    public function test_can_open_and_close_practicum_session(): void
    {
        $user = $this->authenticateUser('dosen');

        $session = PracticumSession::first();
        $this->assertNotNull($session);

        // Open session
        $openResponse = $this->actingAs($user)
            ->postJson("/api/v1/academic/sessions/{$session->id}/open");

        $openResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'OPEN');

        // Close session
        $closeResponse = $this->actingAs($user)
            ->postJson("/api/v1/academic/sessions/{$session->id}/close");

        $closeResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'CLOSED');
    }

    public function test_can_assign_student_role(): void
    {
        $user = $this->authenticateUser('dosen');

        $session = PracticumSession::first();
        $student = Student::first();
        $role = Role::where('slug', 'customer_service')->first();

        $response = $this->actingAs($user)
            ->postJson("/api/v1/academic/sessions/{$session->id}/assign", [
                'student_id' => $student->id,
                'role_id' => $role->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.role_id', $role->id);
    }

    public function test_can_grade_student(): void
    {
        $user = $this->authenticateUser('dosen');

        $session = PracticumSession::first();
        $student = Student::first();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/academic/grades', [
                'session_id' => $session->id,
                'student_id' => $student->id,
                'score' => 92.50,
                'feedback' => 'Sangat teliti dalam pembukuan jurnal dan ramah melayani nasabah.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.score', '92.50');
    }
}

