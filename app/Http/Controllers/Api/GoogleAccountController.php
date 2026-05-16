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
