<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Whatsapp\WhatsappOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsappOtpController extends Controller
{
    public function __construct(
        private readonly WhatsappOtpService $otpService,
    ) {}

    public function request(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'purpose' => ['required', 'in:login'],
        ]);

        if ($validated['purpose'] === 'login') {
            $this->otpService->requestLoginOtp($validated['phone'], $request);
        }

        return response()->json([
            'message' => 'Jika nomor terdaftar, kode OTP telah dikirim via WhatsApp.',
        ]);
    }

    public function verifyLogin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'code' => ['required', 'string', 'size:6'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $this->otpService->verifyLoginOtp($validated['phone'], $validated['code']);

        $deviceName = trim((string) ($validated['device_name'] ?? ''));
        if ($deviceName === '') {
            $deviceName = 'SIAKAD Mobile';
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

    public function requestVerifyNumber(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
        ]);

        $this->otpService->requestVerifyNumber($validated['phone'], $request->user(), $request);

        return response()->json([
            'message' => 'Kode OTP verifikasi telah dikirim via WhatsApp.',
        ]);
    }

    public function verifyNumber(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = $this->otpService->verifyNumberOtp(
            $request->user(),
            $validated['phone'],
            $validated['code'],
        );

        return response()->json([
            'message' => 'Nomor WhatsApp berhasil diverifikasi.',
            'user' => $user->load('roles'),
        ]);
    }

    public function updateWhatsappPreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'whatsapp_notifications_enabled' => ['required', 'boolean'],
        ]);

        $user = $request->user();
        $user->forceFill([
            'whatsapp_notifications_enabled' => (bool) $validated['whatsapp_notifications_enabled'],
        ])->save();

        return response()->json([
            'message' => 'Preferensi notifikasi WhatsApp diperbarui.',
            'user' => $user->load('roles'),
        ]);
    }
}
