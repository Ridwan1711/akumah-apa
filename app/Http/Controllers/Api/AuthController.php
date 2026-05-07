<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Models\User;
use App\Services\Firebase\AccountLinkSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function __construct(
        private readonly AccountLinkSyncService $accountLinkSync,
    ) {}

    /**
     * Login with username and password. Returns Sanctum token + user + roles.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $username = $validated['username'];
        $password = $validated['password'];
        $deviceName = trim((string) ($validated['device_name'] ?? ''));
        if ($deviceName === '') {
            $deviceName = 'SIAKAD Mobile';
        }

        $user = \App\Models\User::where('username', $username)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'username' => [__('auth.failed')],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'username' => ['Akun tidak aktif.'],
            ]);
        }

        $token = $user->createToken($deviceName)->plainTextToken;

        $user->load('roles', 'permissionScopes');
        $primaryRole = $user->role;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
            'role' => $primaryRole,
            'roles' => $user->roles,
            'primary_role' => $primaryRole,
            'permissions' => $user->getAllPermissionNames(),
            'permission_scopes' => $user->permissionScopes->map(fn ($scope) => [
                'permission_name' => $scope->permission_name,
                'scope_key' => $scope->scope_key,
                'scope_value' => $scope->scope_value,
            ])->values(),
        ]);
    }

    /**
     * Logout and revoke current token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    /**
     * Get current authenticated user with roles.
     */
    public function user(Request $request): JsonResponse
    {
        $user = $request->user()->load('roles', 'permissionScopes');
        $primaryRole = $user->role;

        return response()->json([
            'user' => $user,
            'role' => $primaryRole,
            'roles' => $user->roles,
            'primary_role' => $primaryRole,
            'permissions' => $user->getAllPermissionNames(),
            'permission_scopes' => $user->permissionScopes->map(fn ($scope) => [
                'permission_name' => $scope->permission_name,
                'scope_key' => $scope->scope_key,
                'scope_value' => $scope->scope_value,
            ])->values(),
        ]);
    }

    /**
     * Update profile (name, email).
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$request->user()->id],
            'whatsapp_phone' => ['nullable', 'string', 'max:32'],
            'google_connected' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();
        $this->accountLinkSync->syncUser($user);

        return response()->json([
            'message' => 'Profil berhasil diperbarui.',
            'user' => $user->load('roles'),
        ]);
    }

    /**
     * Update password (change password when already logged in).
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $user = $request->user();
        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        $this->revokeOtherTokens($user, $user->currentAccessToken()->id);

        return response()->json(['message' => 'Password berhasil diubah.']);
    }

    /**
     * Force change password (first-time activation).
     */
    public function forceChangePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $user = $request->user();
        $user->update([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
        ]);

        $this->revokeOtherTokens($user, $user->currentAccessToken()->id);

        return response()->json(['message' => 'Password berhasil diubah.']);
    }

    /**
     * List active API tokens (devices) for the current user.
     */
    public function sessions(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentId = $user->currentAccessToken()->id;

        $sessions = $user->tokens()
            ->orderByDesc('last_used_at')
            ->orderByDesc('id')
            ->get(['id', 'name', 'last_used_at', 'created_at'])
            ->map(fn (PersonalAccessToken $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'last_used_at' => $t->last_used_at?->toIso8601String(),
                'created_at' => $t->created_at->toIso8601String(),
                'is_current' => $t->id === $currentId,
            ]);

        return response()->json(['sessions' => $sessions]);
    }

    /**
     * Revoke a specific token (device). Returns whether the current session was revoked.
     */
    public function revokeSession(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $token = PersonalAccessToken::query()
            ->where('id', $id)
            ->where('tokenable_id', $user->id)
            ->where('tokenable_type', $user->getMorphClass())
            ->first();

        if (! $token) {
            return response()->json(['message' => 'Sesi tidak ditemukan.'], 404);
        }

        $wasCurrent = $token->id === $user->currentAccessToken()->id;
        $token->delete();

        return response()->json([
            'message' => 'Sesi berhasil dicabut.',
            'revoked_current' => $wasCurrent,
        ]);
    }

    /**
     * Revoke all tokens except the current one ("logout other devices").
     */
    public function revokeOtherSessions(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentId = $user->currentAccessToken()->id;

        $deleted = $user->tokens()->where('id', '!=', $currentId)->delete();

        return response()->json([
            'message' => 'Sesi di perangkat lain telah dicabut.',
            'revoked_count' => $deleted,
        ]);
    }

    private function revokeOtherTokens(User $user, int $exceptTokenId): void
    {
        $user->tokens()->where('id', '!=', $exceptTokenId)->delete();
    }

    /**
     * Register / refresh an FCM device token for the authenticated user.
     */
    public function registerFcmToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:500'],
            'platform' => ['required', Rule::in(DeviceToken::PLATFORMS)],
            'device_label' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $accessToken = $user->currentAccessToken();
        if ($accessToken === null) {
            return response()->json(['message' => 'Sesi tidak valid.'], 401);
        }

        // `token` is unique globally (one FCM install = one row). Hapus lalu buat ulang agar
        // ganti akun di perangkat yang sama selalu memindahkan baris ke user + sesi aktif.
        DeviceToken::query()->where('token', $validated['token'])->delete();

        $deviceToken = DeviceToken::query()->create([
            'user_id' => $user->id,
            'personal_access_token_id' => $accessToken->id,
            'token' => $validated['token'],
            'platform' => $validated['platform'],
            'device_label' => $validated['device_label'] ?? null,
            'last_used_at' => now(),
        ]);

        return response()->json([
            'message' => 'Token perangkat tersimpan.',
            'device_token' => $deviceToken->only(['id', 'platform', 'device_label', 'last_used_at']),
        ]);
    }

    /**
     * Remove an FCM device token (e.g. on logout).
     */
    public function unregisterFcmToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:500'],
        ]);

        $user = $request->user();

        $deleted = DeviceToken::query()
            ->where('user_id', $user->id)
            ->where('token', $validated['token'])
            ->delete();

        return response()->json([
            'message' => $deleted ? 'Token perangkat dihapus.' : 'Token tidak ditemukan.',
            'deleted' => (bool) $deleted,
        ]);
    }

    /**
     * Upload profile photo (official/custom) for current user.
     */
    public function uploadProfilePhoto(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['official', 'custom'])],
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = $request->user();
        $type = (string) $validated['type'];
        /** @var UploadedFile $photo */
        $photo = $validated['photo'];

        if ($type === 'official' && $user->has_official_photo && ! $user->isAdmin()) {
            return response()->json([
                'message' => 'Foto resmi sudah terisi dan hanya admin yang dapat menggantinya.',
            ], 403);
        }

        $field = $type === 'official' ? 'official_photo_path' : 'custom_photo_path';
        $oldPath = $user->{$field};
        if (is_string($oldPath) && $oldPath !== '' && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        $stored = $photo->store("profile-photos/{$user->id}", 'public');
        $user->forceFill([$field => $stored])->save();
        $user->refresh();

        return response()->json([
            'message' => $type === 'official' ? 'Foto resmi berhasil diunggah.' : 'Foto kustom berhasil diunggah.',
            'user' => $user->load('roles'),
        ]);
    }

    /**
     * Remove custom profile photo and fallback to official photo.
     */
    public function removeCustomProfilePhoto(Request $request): JsonResponse
    {
        $user = $request->user();
        $oldPath = $user->custom_photo_path;
        if (is_string($oldPath) && $oldPath !== '' && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }
        $user->forceFill(['custom_photo_path' => null])->save();
        $user->refresh();

        return response()->json([
            'message' => 'Foto kustom dihapus. Avatar kembali ke foto resmi.',
            'user' => $user->load('roles'),
        ]);
    }

    /**
     * Request password reset link (sends email).
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink($validated);

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json(['message' => 'Link reset password telah dikirim ke email Anda.']);
    }

    /**
     * Reset password with token from email.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            $validated,
            function ($user) use ($validated) {
                $user->forceFill([
                    'password' => Hash::make($validated['password']),
                    'remember_token' => \Illuminate\Support\Str::random(60),
                ])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json(['message' => 'Password berhasil direset.']);
    }
}
