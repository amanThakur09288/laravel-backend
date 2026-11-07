<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        if (!$request->hasSession()) {
            return response()->json([
                'success' => false,
                'message' => 'Login is only available from the admin panel.',
            ], 419);
        }

        $request->merge([
            'username' => Str::lower(
                trim((string) $request->input('username'))
            ),
        ]);

        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $user = User::query()
            ->where('username', $credentials['username'])
            ->whereIn('role', [
                User::ROLE_ADMIN,
                User::ROLE_TEACHER,
            ])
            ->where('is_active', true)
            ->first();

        if (
            !$user ||
            !Auth::attempt([
                'username' => $credentials['username'],
                'password' => $credentials['password'],
            ])
        ) {
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

    public function user(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'user' => $this->userData($request->user()),
            ],
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
