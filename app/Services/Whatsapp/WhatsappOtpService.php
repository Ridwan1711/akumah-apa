<?php

namespace App\Services\Whatsapp;

use App\Models\User;
use App\Models\WhatsappOtpCode;
use App\Services\Finance\FinanceWhatsappPhone;
use App\Services\Whatsapp\Exceptions\WhatsappHaltedException;
use App\Services\Whatsapp\Exceptions\WhatsappNotReadyException;
use App\Services\Whatsapp\Exceptions\WhatsappRateLimitedException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class WhatsappOtpService
{
    private const OTP_TTL_MINUTES = 5;

    private const MAX_VERIFY_ATTEMPTS = 5;

    public function __construct(
        private readonly WhatsappClient $whatsappClient,
    ) {}

    public function requestLoginOtp(string $rawPhone, Request $request): void
    {
        $phone = $this->normalizeOrFail($rawPhone);
        $this->assertRequestAllowed($phone, $request, 'otp-login-phone');
        $this->assertRequestAllowed($request->ip() ?? 'unknown', $request, 'otp-login-ip', 20, 3600);

        $user = User::query()
            ->where('whatsapp_phone', $phone)
            ->whereNotNull('whatsapp_phone_verified_at')
            ->where('is_active', true)
            ->first();

        if ($user === null) {
            return;
        }

        $this->issueAndSend($phone, WhatsappOtpCode::PURPOSE_LOGIN, $user->id, $request, 'login');
    }

    public function requestVerifyNumber(string $rawPhone, User $user, Request $request): void
    {
        $phone = $this->normalizeOrFail($rawPhone);
        $this->assertRequestAllowed($phone, $request, 'otp-verify-phone');
        $this->assertRequestAllowed($request->ip() ?? 'unknown', $request, 'otp-verify-ip', 20, 3600);

        $taken = User::query()
            ->where('whatsapp_phone', $phone)
            ->where('id', '!=', $user->id)
            ->whereNotNull('whatsapp_phone_verified_at')
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'phone' => ['Nomor WhatsApp sudah digunakan akun lain.'],
            ]);
        }

        $user->forceFill([
            'whatsapp_phone' => $phone,
            'whatsapp_phone_verified_at' => null,
        ])->save();

        $this->issueAndSend($phone, WhatsappOtpCode::PURPOSE_VERIFY_NUMBER, $user->id, $request, 'verifikasi nomor');
    }

    public function verifyLoginOtp(string $rawPhone, string $code): User
    {
        $phone = $this->normalizeOrFail($rawPhone);
        $otp = $this->findActiveOtp($phone, WhatsappOtpCode::PURPOSE_LOGIN);
        $this->assertCodeValid($otp, $code);

        $user = User::query()
            ->where('whatsapp_phone', $phone)
            ->whereNotNull('whatsapp_phone_verified_at')
            ->where('is_active', true)
            ->first();

        if ($user === null) {
            throw ValidationException::withMessages([
                'code' => ['Kode OTP tidak valid.'],
            ]);
        }

        $this->consume($otp);

        return $user;
    }

    public function verifyNumberOtp(User $user, string $rawPhone, string $code): User
    {
        $phone = $this->normalizeOrFail($rawPhone);
        $otp = $this->findActiveOtp($phone, WhatsappOtpCode::PURPOSE_VERIFY_NUMBER);

        if ((int) $otp->user_id !== (int) $user->id) {
            throw ValidationException::withMessages([
                'code' => ['Kode OTP tidak valid.'],
            ]);
        }

        $this->assertCodeValid($otp, $code);
        $this->consume($otp);

        $user->forceFill([
            'whatsapp_phone' => $phone,
            'whatsapp_phone_verified_at' => now(),
            'whatsapp_notifications_enabled' => true,
        ])->save();

        return $user->fresh();
    }

    private function issueAndSend(
        string $phone,
        string $purpose,
        int $userId,
        Request $request,
        string $purposeLabel,
    ): void {
        WhatsappOtpCode::query()
            ->where('phone', $phone)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->delete();

        $plainCode = (string) random_int(100000, 999999);
        $ref = Str::upper(Str::random(6));

        WhatsappOtpCode::query()->create([
            'phone' => $phone,
            'code_hash' => Hash::make($plainCode),
            'purpose' => $purpose,
            'user_id' => $userId,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
        ]);

        $appName = (string) config('app.name', 'SIAKAD');
        $message = "{$appName} Kode OTP {$purposeLabel}: {$plainCode}\n"
            .'Berlaku '.self::OTP_TTL_MINUTES." menit. Jangan bagikan kode ini ke siapa pun.\n"
            ."Ref: {$ref}";

        try {
            $this->whatsappClient->send(
                $phone,
                $message,
                'otp',
                applyRateLimit: false,
                sessionSlug: config('services.wa.session_routes.otp', 'pesantren'),
            );
        } catch (WhatsappRateLimitedException|WhatsappNotReadyException|WhatsappHaltedException $e) {
            throw ValidationException::withMessages([
                'phone' => ['WhatsApp sementara tidak dapat mengirim OTP. Coba lagi nanti.'],
            ]);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages([
                'phone' => ['Gagal mengirim OTP via WhatsApp.'],
            ]);
        }
    }

    private function findActiveOtp(string $phone, string $purpose): WhatsappOtpCode
    {
        $otp = WhatsappOtpCode::query()
            ->where('phone', $phone)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if ($otp === null || $otp->isExpired()) {
            throw ValidationException::withMessages([
                'code' => ['Kode OTP tidak valid atau sudah kedaluwarsa.'],
            ]);
        }

        return $otp;
    }

    private function assertCodeValid(WhatsappOtpCode $otp, string $code): void
    {
        if ($otp->attempts >= self::MAX_VERIFY_ATTEMPTS) {
            $this->consume($otp);
            throw ValidationException::withMessages([
                'code' => ['Terlalu banyak percobaan. Minta kode baru.'],
            ]);
        }

        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');
            throw ValidationException::withMessages([
                'code' => ['Kode OTP tidak valid.'],
            ]);
        }
    }

    private function consume(WhatsappOtpCode $otp): void
    {
        $otp->forceFill(['consumed_at' => now()])->save();
    }

    private function normalizeOrFail(string $rawPhone): string
    {
        $phone = FinanceWhatsappPhone::normalize($rawPhone);
        if ($phone === null) {
            throw ValidationException::withMessages([
                'phone' => ['Nomor WhatsApp tidak valid.'],
            ]);
        }

        return $phone;
    }

    private function assertRequestAllowed(
        string $key,
        Request $request,
        string $rateLimiterName,
        int $maxAttempts = 1,
        int $decaySeconds = 60,
    ): void {
        $limiterKey = $rateLimiterName.':'.hash('sha256', $key);

        if (RateLimiter::tooManyAttempts($limiterKey, $maxAttempts)) {
            throw ValidationException::withMessages([
                'phone' => ['Terlalu sering meminta OTP. Coba lagi nanti.'],
            ]);
        }

        RateLimiter::hit($limiterKey, $decaySeconds);

        if ($rateLimiterName === 'otp-login-phone' || $rateLimiterName === 'otp-verify-phone') {
            $dailyKey = $rateLimiterName.'-daily:'.hash('sha256', $key);
            if (RateLimiter::tooManyAttempts($dailyKey, 5)) {
                throw ValidationException::withMessages([
                    'phone' => ['Batas permintaan OTP harian tercapai.'],
                ]);
            }
            RateLimiter::hit($dailyKey, 86_400);
        }
    }
}
