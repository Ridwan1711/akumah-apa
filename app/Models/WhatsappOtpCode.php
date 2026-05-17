<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappOtpCode extends Model
{
    protected $table = 'wa_otp_codes';

    public const PURPOSE_LOGIN = 'login';

    public const PURPOSE_VERIFY_NUMBER = 'verify_number';

    protected $fillable = [
        'phone',
        'code_hash',
        'purpose',
        'user_id',
        'attempts',
        'ip_address',
        'user_agent',
        'expires_at',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }
}
