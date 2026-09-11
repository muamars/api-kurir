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

        $tokenResult = $user->createToken('api-token');

        if ($user->hasRole('Kurir')) {
            $tokenResult->accessToken->update([
                'expires_at' => now()->addHours(24),
            ]);
        }

        $token = $tokenResult->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'profile_photo' => $user->profile_photo,
                    'profile_photo_url' => $user->profile_photo_url,
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
        $request->user()->tokens()->delete();

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
                'profile_photo' => $user->profile_photo,
                'profile_photo_url' => $user->profile_photo_url,
                'is_active' => $user->is_active,
                'division' => $user->division,
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ],
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'phone' => 'nullable|string|max:20',
            'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ], [
            'name.required' => 'Nama wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah digunakan.',
            'profile_photo.image' => 'File harus berupa gambar.',
            'profile_photo.max' => 'Ukuran gambar maksimal 2MB.',
        ]);

        $updateData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
        ];

        if ($request->hasFile('profile_photo')) {
            if ($user->profile_photo && \Illuminate\Support\Facades\Storage::disk('public')->exists($user->profile_photo)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($user->profile_photo);
            }
            $updateData['profile_photo'] = $request->file('profile_photo')->store('profile_photos', 'public');
        }

        $user->update($updateData);

        return response()->json([
            'message' => 'Profil berhasil diperbarui',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'profile_photo' => $user->profile_photo,
                'profile_photo_url' => $user->profile_photo_url,
                'is_active' => $user->is_active,
                'division' => $user->division,
                'roles' => $user->getRoleNames(),
            ],
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $request->validate([
            'current_password' => 'required',
            'password' => 'required|string|min:6|confirmed',
        ], [
            'current_password.required' => 'Password saat ini wajib diisi.',
            'password.required' => 'Password baru wajib diisi.',
            'password.min' => 'Password baru minimal 6 karakter.',
            'password.confirmed' => 'Konfirmasi password baru tidak cocok.',
        ]);

        if (! \Illuminate\Support\Facades\Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Password saat ini salah.',
            ], 422);
        }

        $user->update([
            'password' => \Illuminate\Support\Facades\Hash::make($request->password),
        ]);

        return response()->json([
            'message' => 'Password berhasil diperbarui',
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
