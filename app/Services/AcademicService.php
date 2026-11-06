<?php

namespace App\Services;

use App\Models\AcademicClass;
use App\Models\Student;
use App\Models\Lecturer;
use App\Models\Practicum;
use App\Models\PracticumGroup;
use App\Models\PracticumSession;
use App\Models\PracticumAssignment;
use App\Models\PracticumGrade;
use App\Models\PracticumActivity;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Exception;

class AcademicService
{
    public function getClasses()
    {
        return AcademicClass::with(['lecturer.user'])
            ->withCount(['students', 'groups', 'practicums'])
            ->orderBy('class_name')
            ->get();
    }

    public function createClass(array $data): AcademicClass
    {
        return AcademicClass::create([
            'class_name' => $data['class_name'],
            'lecturer_id' => $data['lecturer_id'] ?? null,
            'academic_year' => $data['academic_year'] ?? '2026/2027',
            'semester' => $data['semester'] ?? 'Genap',
        ])->load('lecturer.user');
    }

    public function getStudents(?int $classId = null)
    {
        $query = Student::with(['user', 'academicClass']);
        if ($classId) {
            $query->where('class_id', $classId);
        }
        return $query->get();
    }

    public function getGroups(?int $classId = null)
    {
        $query = PracticumGroup::with(['academicClass', 'members.user']);
        if ($classId) {
            $query->where('class_id', $classId);
        }
        return $query->get();
    }

    public function createGroup(array $data): PracticumGroup
    {
        return DB::transaction(function () use ($data) {
            $group = PracticumGroup::create([
                'class_id' => $data['class_id'],
                'group_name' => $data['group_name'],
                'description' => $data['description'] ?? null,
            ]);

            if (!empty($data['student_ids'])) {
                $group->members()->sync($data['student_ids']);
            }

            return $group->load(['academicClass', 'members.user']);
        });
    }

    public function getSessions(?int $practicumId = null, ?string $status = null)
    {
        $query = PracticumSession::with([
            'practicum.academicClass',
            'group.members.user',
            'assignments.student.user',
            'assignments.role',
            'grades.student.user',
        ])->orderByDesc('created_at');

        if ($practicumId) {
            $query->where('practicum_id', $practicumId);
        }

        if ($status) {
            $query->where('status', strtoupper($status));
        }

        return $query->get();
    }

    public function createSession(array $data): PracticumSession
    {
        return PracticumSession::create([
            'practicum_id' => $data['practicum_id'],
            'group_id' => $data['group_id'],
            'session_name' => $data['session_name'],
            'virtual_initial_balance' => $data['virtual_initial_balance'] ?? 50000000.00,
            'status' => 'OPEN',
            'start_time' => now(),
        ])->load(['practicum', 'group']);
    }

    public function openSession(int $sessionId, int $userId): PracticumSession
    {
        $session = PracticumSession::findOrFail($sessionId);
        $session->update([
            'status' => 'OPEN',
            'start_time' => now(),
            'end_time' => null,
        ]);

        PracticumActivity::create([
            'session_id' => $session->id,
            'user_id' => $userId,
            'activity_type' => 'SESSION_OPENED',
            'description' => "Sesi praktikum {$session->session_name} dibuka dengan saldo awal Rp " . number_format($session->virtual_initial_balance, 0, ',', '.'),
        ]);

        return $session->load(['practicum', 'group', 'assignments.role', 'assignments.student.user']);
    }

    public function closeSession(int $sessionId, int $userId): PracticumSession
    {
        $session = PracticumSession::findOrFail($sessionId);
        $session->update([
            'status' => 'CLOSED',
            'end_time' => now(),
        ]);

        PracticumActivity::create([
            'session_id' => $session->id,
            'user_id' => $userId,
            'activity_type' => 'SESSION_CLOSED',
            'description' => "Sesi praktikum {$session->session_name} telah ditutup secara resmi.",
        ]);

        return $session->load(['practicum', 'group', 'assignments.role', 'assignments.student.user']);
    }

    public function assignStudentRole(int $sessionId, int $studentId, int $roleId, int $assignedBy): PracticumAssignment
    {
        return DB::transaction(function () use ($sessionId, $studentId, $roleId, $assignedBy) {
            $session = PracticumSession::findOrFail($sessionId);
            $student = Student::with('user')->findOrFail($studentId);
            $role = Role::findOrFail($roleId);

            // Update or create assignment
            $assignment = PracticumAssignment::updateOrCreate([
                'session_id' => $session->id,
                'student_id' => $student->id,
            ], [
                'role_id' => $role->id,
                'assigned_by' => $assignedBy,
            ]);

            // Assign role to student's user account so they can immediately login/switch to this role
            if ($student->user) {
                DB::table('role_user')->updateOrInsert([
                    'user_id' => $student->user->id,
                    'role_id' => $role->id,
                ], [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            PracticumActivity::create([
                'session_id' => $session->id,
                'user_id' => $assignedBy,
                'activity_type' => 'ROLE_ASSIGNED',
                'description' => "Penugasan peran {$role->name} kepada {$student->user->name} untuk sesi {$session->session_name}",
            ]);

            return $assignment->load(['student.user', 'role', 'session']);
        });
    }

    public function gradeStudent(int $sessionId, int $studentId, int $lecturerId, float $score, ?string $feedback): PracticumGrade
    {
        if ($score < 0 || $score > 100) {
            throw new Exception('Nilai harus berada dalam rentang 0 hingga 100.');
        }

        $session = PracticumSession::findOrFail($sessionId);
        $student = Student::findOrFail($studentId);

        $grade = PracticumGrade::updateOrCreate([
            'session_id' => $session->id,
            'student_id' => $student->id,
        ], [
            'lecturer_id' => $lecturerId,
            'score' => $score,
            'feedback' => $feedback,
        ]);

        return $grade->load(['student.user', 'session', 'lecturer.user']);
    }
}

