<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Auth\GoogleAccountLinkService;
use App\Services\Firebase\AccountLinkSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoogleAccountController extends Controller
{
    public function __construct(
        private readonly GoogleAccountLinkService $googleAccountLink,
        private readonly AccountLinkSyncService $accountLinkSync,
    ) {}

    /**
     * Login with Google for users who already linked google_id on their account.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'access_token' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $deviceName = trim((string) ($validated['device_name'] ?? ''));
        if ($deviceName === '') {
            $deviceName = 'SIAKAD Mobile';
        }

        $user = $this->googleAccountLink->findUserForLogin($validated['access_token']);
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

    public function link(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'access_token' => ['required', 'string'],
        ]);

        $user = $this->googleAccountLink->link(
            $request->user(),
            $validated['access_token'],
        );

        $this->accountLinkSync->syncUser($user);

        return response()->json([
            'message' => 'Akun Google berhasil dihubungkan.',
            'user' => $user->load('roles'),
        ]);
    }

    public function unlink(Request $request): JsonResponse
    {
        $user = $this->googleAccountLink->unlink($request->user());
        $this->accountLinkSync->syncUser($user);

        return response()->json([
            'message' => 'Akun Google berhasil diputus.',
            'user' => $user->load('roles'),
        ]);
    }
}
