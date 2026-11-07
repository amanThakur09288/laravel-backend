<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StudentController extends Controller
{
    public function index(): JsonResponse
    {
        $students = User::query()
            ->where('role', User::ROLE_USER)
            ->withCount('quizAttempts')
            ->latest()
            ->get()
            ->map(fn (User $student) => $this->studentData($student));

        return response()->json([
            'success' => true,
            'data' => $students,
        ]);
    }

    public function show(User $student): JsonResponse
    {
        $this->ensureStudent($student);

        $student->loadCount('quizAttempts');

        return response()->json([
            'success' => true,
            'data' => $this->studentData($student),
        ]);
    }

    public function update(Request $request, User $student): JsonResponse
    {
        $this->ensureStudent($student);

        $validated = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
            'username' => [
                'sometimes',
                'required',
                'string',
                'min:3',
                'max:50',
                'regex:/^[a-zA-Z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($student->id),
            ],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($student->id),
            ],
            'password' => [
                'sometimes',
                'nullable',
                'confirmed',
                Password::min(8)->letters()->numbers(),
            ],
        ]);

        if (array_key_exists('name', $validated)) {
            $student->name = $validated['name'];
        }

        if (array_key_exists('username', $validated)) {
            $student->username = strtolower($validated['username']);
        }

        if (array_key_exists('email', $validated)) {
            $student->email = strtolower($validated['email']);
        }

        if (!empty($validated['password'])) {
            $student->password = $validated['password'];
        }

        $student->save();

        // Revoke existing database sessions after a password reset.
        if (!empty($validated['password'])) {
            $this->revokeDatabaseSessions($student);
        }

        $student->loadCount('quizAttempts');

        return response()->json([
            'success' => true,
            'message' => 'Student account updated successfully.',
            'data' => $this->studentData($student),
        ]);
    }

    public function updateStatus(
        Request $request,
        User $student
    ): JsonResponse {
        $this->ensureStudent($student);

        $validated = $request->validate([
            'is_active' => [
                'required',
                'boolean',
            ],
        ]);

        $student->is_active = (bool) $validated['is_active'];
        $student->save();

        if (!$student->is_active) {
            $this->revokeDatabaseSessions($student);
        }

        return response()->json([
            'success' => true,
            'message' => $student->is_active
                ? 'Student account activated successfully.'
                : 'Student account deactivated successfully.',
            'data' => $this->studentData($student),
        ]);
    }

    /**
     * Permanently delete only students without quiz history.
     * Students with quiz attempts must be deactivated instead.
     */
    public function destroy(User $student): JsonResponse
    {
        $hasQuizHistory = DB::transaction(function () use ($student) {
            $lockedStudent = User::query()
                ->whereKey($student->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureStudent($lockedStudent);

            if ($lockedStudent->quizAttempts()->exists()) {
                return true;
            }

            $this->revokeDatabaseSessions($lockedStudent);

            $lockedStudent->delete();

            return false;
        });

        if ($hasQuizHistory) {
            return response()->json([
                'success' => false,
                'message' => 'This student has quiz history and cannot be deleted. Deactivate the account to prevent further access while preserving results.',
            ], 409);
        }

        return response()->json([
            'success' => true,
            'message' => 'Student account deleted successfully.',
        ]);
    }

    private function ensureStudent(User $student): void
    {
        abort_unless(
            $student->role === User::ROLE_USER,
            404,
            'Student account not found.'
        );
    }

    /**
     * Database sessions are explicitly removed when that driver is active.
     * Other session drivers are handled through account status checks.
     */
    private function revokeDatabaseSessions(User $student): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        $table = config('session.table', 'sessions');

        if (!\Illuminate\Support\Facades\Schema::hasTable($table)) {
            return;
        }

        DB::table($table)
            ->where('user_id', $student->id)
            ->delete();
    }

    private function studentData(User $student): array
    {
        return [
            'id' => $student->id,
            'name' => $student->name,
            'username' => $student->username,
            'email' => $student->email,
            'role' => $student->role,
            'is_active' => (bool) $student->is_active,
            'quiz_attempts_count' => (int) (
                $student->quiz_attempts_count ?? 0
            ),
            'created_at' => $student->created_at,
        ];
    }
}
