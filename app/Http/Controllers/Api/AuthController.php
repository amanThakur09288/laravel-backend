<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // Only users with this role can log in to the admin panel
    private const ADMIN_ROLE = 'admin';

    /**
     * Login admin user with username and password.
     * (Limited to 5 attempts per minute, see routes/api.php and AppServiceProvider.)
     */
    public function login(Request $request): JsonResponse
    {
        // A session only exists for requests that come from the admin panel
        // (see SANCTUM_STATEFUL_DOMAINS in .env). Anything else is refused.
        if (! $request->hasSession()) {
            return response()->json([
                'success' => false,
                'message' => 'Login is only available from the admin panel.',
            ], 419);
        }

        // "Admin" and "admin " are the same username
        $request->merge([
            'username' => Str::lower(trim((string) $request->input('username'))),
        ]);

        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        // The role is part of the check, so a normal user can never get in.
        // Wrong username, wrong password and "not an admin" all show the same message.
        if (! Auth::attempt([...$credentials, 'role' => self::ADMIN_ROLE])) {
            throw ValidationException::withMessages([
                'username' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Prevent session fixation
        $request->session()->regenerate();

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'user' => $this->userData(Auth::user()),
            ],
        ]);
    }

    /**
     * Logout current user.
     */
    public function logout(Request $request): JsonResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'success' => true,
            'message' => 'Logout successful.',
        ]);
    }

    /**
     * Get currently authenticated user.
     */
    public function user(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'user' => $this->userData($request->user()),
            ],
        ]);
    }

    // Only these fields are ever sent to the browser (never the password)
    private function userData($user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
        ];
    }
}