<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAccountLinkService
{
    public function link(User $user, string $accessToken): User
    {
        $googleUser = $this->resolveGoogleUser($accessToken);

        $googleId = (string) $googleUser->getId();
        if ($googleId === '') {
            throw ValidationException::withMessages([
                'access_token' => ['Data akun Google tidak lengkap.'],
            ]);
        }

        $conflict = User::query()
            ->where('google_id', $googleId)
            ->where('id', '!=', $user->id)
            ->exists();

        if ($conflict) {
            throw new HttpResponseException(response()->json([
                'message' => 'Akun Google ini sudah terhubung ke pengguna lain.',
            ], 409));
        }

        $user->forceFill([
            'google_id' => $googleId,
            'google_email' => $googleUser->getEmail(),
            'google_connected' => true,
        ])->save();

        return $user->fresh();
    }

    public function unlink(User $user): User
    {
        $user->forceFill([
            'google_id' => null,
            'google_email' => null,
            'google_connected' => false,
        ])->save();

        return $user->fresh();
    }

    private function resolveGoogleUser(string $accessToken): \Laravel\Socialite\Contracts\User
    {
        try {
            return Socialite::driver('google')->stateless()->userFromToken($accessToken);
        } catch (Throwable $e) {
            Log::debug('Google token verification failed', ['error' => $e->getMessage()]);

            throw ValidationException::withMessages([
                'access_token' => ['Token Google tidak valid atau sudah kedaluwarsa.'],
            ]);
        }
    }
}
