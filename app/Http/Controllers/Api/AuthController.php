<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Login\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->validated())) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        $user = Auth::user();

        // Only block login for non-Kurir users when inactive
        // Kurir can login even when inactive to toggle their status
        if (! $user->is_active && ! $user->hasRole('Kurir')) {
            return response()->json([
                'message' => 'Akun Anda telah dinonaktifkan. Silakan hubungi administrator.',
            ], 403);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'is_active' => $user->is_active,
                    'division' => $user->division,
                    'roles' => $user->getRoleNames(),
                    'permissions' => $user->getAllPermissions()->pluck('name'),
                ],
                'token' => $token,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout successful',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'is_active' => $user->is_active,
                'division' => $user->division,
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ],
        ]);
    }

    /**
     * Generate permanent API token for testing
     */
    public function generateTestToken(Request $request): JsonResponse
    {
        // Only allow in development/testing environment
        if (! app()->environment(['local', 'testing'])) {
            return response()->json([
                'message' => 'Token generation only available in development environment',
            ], 403);
        }

        $request->validate([
            'email' => 'required|email|exists:users,email',
            'token_name' => 'string|max:255',
            'expires_in_days' => 'nullable|integer|min:1|max:365',
        ]);

        $user = \App\Models\User::where('email', $request->email)->first();

        // Only block token generation for non-Kurir users when inactive
        if (! $user->is_active && ! $user->hasRole('Kurir')) {
            return response()->json([
                'message' => 'User account is inactive',
            ], 403);
        }

        $tokenName = $request->token_name ?? 'test-token-'.now()->format('Y-m-d-H-i-s');
        $tokenResult = $user->createToken($tokenName, ['*']);

        // Set expiration if specified
        if ($request->expires_in_days) {
            $expirationDate = now()->addDays($request->expires_in_days);
            $tokenResult->accessToken->update([
                'expires_at' => $expirationDate,
            ]);
        }

        return response()->json([
            'message' => 'Test token generated successfully',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->getRoleNames(),
                    'division' => $user->division,
                ],
                'token' => $tokenResult->plainTextToken,
                'token_name' => $tokenName,
                'expires_at' => $request->expires_in_days ?
                    now()->addDays($request->expires_in_days)->toISOString() : null,
                'is_permanent' => ! $request->expires_in_days,
            ],
        ]);
    }
}
