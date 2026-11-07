<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AdminUserController extends Controller
{
    /**
     * List all teacher accounts.
     */
    public function index(): JsonResponse
    {
        $admins = User::query()
            ->where('role', User::ROLE_TEACHER)
            ->select([
                'id',
                'name',
                'username',
                'email',
                'role',
                'is_active',
                'created_at',
            ])
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $admins,
        ]);
    }

    /**
     * Create a teacher account.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'regex:/^[a-zA-Z0-9._-]+$/',
                'unique:users,username',
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
            ],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->letters()->numbers(),
            ],
        ]);

        $admin = User::create([
            'name' => $validated['name'],
            'username' => strtolower($validated['username']),
            'email' => strtolower($validated['email']),
            'password' => $validated['password'],
            'role' => User::ROLE_TEACHER,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Teacher account created successfully.',
            'data' => $this->adminData($admin),
        ], 201);
    }

    /**
     * View one teacher.
     */
    public function show(User $admin): JsonResponse
    {
        $this->ensureTeacher($admin);

        return response()->json([
            'success' => true,
            'data' => $this->adminData($admin),
        ]);
    }

    /**
     * Update teacher details.
     */
    public function update(Request $request, User $admin): JsonResponse
    {
        $this->ensureTeacher($admin);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'username' => [
                'sometimes',
                'required',
                'string',
                'min:3',
                'max:50',
                'regex:/^[a-zA-Z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($admin->id),
            ],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($admin->id),
            ],
            'password' => [
                'sometimes',
                'nullable',
                'confirmed',
                Password::min(8)->letters()->numbers(),
            ],
        ]);

        if (isset($validated['name'])) {
            $admin->name = $validated['name'];
        }

        if (isset($validated['username'])) {
            $admin->username = strtolower($validated['username']);
        }

        if (isset($validated['email'])) {
            $admin->email = strtolower($validated['email']);
        }

        if (!empty($validated['password'])) {
            $admin->password = $validated['password'];

            // End all existing sessions for this teacher after password reset.
            DB::table('sessions')
                ->where('user_id', $admin->id)
                ->delete();
        }

        $admin->save();

        return response()->json([
            'success' => true,
            'message' => 'Teacher account updated successfully.',
            'data' => $this->adminData($admin->fresh()),
        ]);
    }

    /**
     * Activate or deactivate a teacher.
     */
    public function updateStatus(Request $request, User $admin): JsonResponse
    {
        $this->ensureTeacher($admin);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $admin->is_active = $validated['is_active'];
        $admin->save();

        if (!$admin->is_active) {
            DB::table('sessions')
                ->where('user_id', $admin->id)
                ->delete();
        }

        return response()->json([
            'success' => true,
            'message' => $admin->is_active
                ? 'Teacher account activated successfully.'
                : 'Teacher account deactivated successfully.',
            'data' => $this->adminData($admin->fresh()),
        ]);
    }

    /**
     * Do not allow this controller to modify Main Admin or student accounts.
     */
    private function ensureTeacher(User $admin): void
    {
        abort_unless(
            $admin->role === User::ROLE_TEACHER,
            404,
            'Teacher account not found.'
        );
    }

    /**
     * Safe response fields.
     */
    private function adminData(User $admin): array
    {
        return [
            'id' => $admin->id,
            'name' => $admin->name,
            'username' => $admin->username,
            'email' => $admin->email,
            'role' => $admin->role,
            'is_active' => $admin->is_active,
            'created_at' => $admin->created_at,
        ];
    }
}