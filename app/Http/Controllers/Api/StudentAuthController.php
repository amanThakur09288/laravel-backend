<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;

class StudentAuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $request->merge([
            'username' => Str::lower(
                trim((string) $request->input('username'))
            ),
            'email' => Str::lower(
                trim((string) $request->input('email'))
            ),
        ]);

        if (!$request->hasSession()) {
            return response()->json([
                'success' => false,
                'message' => 'Student authentication requires a stateful client session.',
            ], 419);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'username' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'regex:/^[a-z0-9._-]+$/',
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

        $student = DB::transaction(function () use ($validated) {
            return User::create([
                'name' => $validated['name'],
                'username' => $validated['username'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'role' => User::ROLE_USER,
            ]);
        });

        Auth::login($student);

        $request->session()->regenerate();

        return response()->json([
            'success' => true,
            'message' => 'Student account created successfully.',
            'data' => [
                'user' => $this->userData($student),
            ],
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $request->merge([
            'username' => Str::lower(
                trim((string) $request->input('username'))
            ),
        ]);

        if (!$request->hasSession()) {
            return response()->json([
                'success' => false,
                'message' => 'Student authentication requires a stateful client session.',
            ], 419);
        }

        $credentials = $request->validate([
            'username' => [
                'required',
                'string',
                'max:50',
            ],
            'password' => [
                'required',
                'string',
                'max:255',
            ],
        ]);

        if (!Auth::attempt([
            'username' => $credentials['username'],
            'password' => $credentials['password'],
            'role' => User::ROLE_USER,
            'is_active' => true,
        ])) {
            throw ValidationException::withMessages([
                'username' => [
                    'The provided credentials are incorrect.',
                ],
            ]);
        }

        $request->session()->regenerate();

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'user' => $this->userData(Auth::user()),
            ],
        ]);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'user' => $this->userData($request->user()),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'success' => true,
            'message' => 'Logout successful.',
        ]);
    }

    private function userData(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => (bool) $user->is_active,
        ];
    }
}
