<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AcademicService;
use App\Models\Lecturer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Exception;

class AcademicController extends Controller
{
    protected AcademicService $academicService;

    public function __construct(AcademicService $academicService)
    {
        $this->academicService = $academicService;
    }

    public function getClasses(): JsonResponse
    {
        $classes = $this->academicService->getClasses();

        return response()->json([
            'success' => true,
            'data' => $classes,
        ]);
    }

    public function storeClass(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'class_name' => 'required|string|max:100',
            'lecturer_id' => 'nullable|exists:lecturers,id',
            'academic_year' => 'nullable|string|max:20',
            'semester' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $class = $this->academicService->createClass($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Kelas praktikum berhasil dibuat.',
            'data' => $class,
        ], 201);
    }

    public function getStudents(Request $request): JsonResponse
    {
        $students = $this->academicService->getStudents($request->class_id);

        return response()->json([
            'success' => true,
            'data' => $students,
        ]);
    }

    public function getGroups(Request $request): JsonResponse
    {
        $groups = $this->academicService->getGroups($request->class_id);

        return response()->json([
            'success' => true,
            'data' => $groups,
        ]);
    }

    public function storeGroup(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'class_id' => 'required|exists:classes,id',
            'group_name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'student_ids' => 'nullable|array',
            'student_ids.*' => 'exists:students,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi pembuatan kelompok gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $group = $this->academicService->createGroup($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Kelompok praktikum berhasil dibuat.',
            'data' => $group,
        ], 201);
    }

    public function getSessions(Request $request): JsonResponse
    {
        $sessions = $this->academicService->getSessions(
            $request->practicum_id,
            $request->status
        );

        return response()->json([
            'success' => true,
            'data' => $sessions,
        ]);
    }

    public function storeSession(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'practicum_id' => 'required|exists:practicums,id',
            'group_id' => 'required|exists:practicum_groups,id',
            'session_name' => 'required|string|max:150',
            'virtual_initial_balance' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi sesi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $session = $this->academicService->createSession($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Sesi praktikum berhasil dijadwalkan.',
            'data' => $session,
        ], 201);
    }

    public function openSession(Request $request, int $id): JsonResponse
    {
        try {
            $session = $this->academicService->openSession($id, $request->user()->id);

            return response()->json([
                'success' => true,
                'message' => "Sesi praktikum {$session->session_name} telah dibuka.",
                'data' => $session,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function closeSession(Request $request, int $id): JsonResponse
    {
        try {
            $session = $this->academicService->closeSession($id, $request->user()->id);

            return response()->json([
                'success' => true,
                'message' => "Sesi praktikum {$session->session_name} telah ditutup secara resmi.",
                'data' => $session,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function assignRole(Request $request, int $sessionId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'student_id' => 'required|exists:students,id',
            'role_id' => 'required|exists:roles,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi penugasan peran gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $assignment = $this->academicService->assignStudentRole(
                $sessionId,
                $request->student_id,
                $request->role_id,
                $request->user()->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Peran mahasiswa dalam sesi berhasil ditetapkan.',
                'data' => $assignment,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function gradeStudent(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'session_id' => 'required|exists:practicum_sessions,id',
            'student_id' => 'required|exists:students,id',
            'score' => 'required|numeric|min:0|max:100',
            'feedback' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi penilaian gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Get lecturer ID from user if user is lecturer, or first lecturer
        $lecturer = Lecturer::where('user_id', $request->user()->id)->first() ?? Lecturer::first();

        try {
            $grade = $this->academicService->gradeStudent(
                $request->session_id,
                $request->student_id,
                $lecturer->id,
                (float) $request->score,
                $request->feedback
            );

            return response()->json([
                'success' => true,
                'message' => 'Penilaian mahasiswa berhasil disimpan.',
                'data' => $grade,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}

