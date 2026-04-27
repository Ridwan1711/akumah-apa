<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceToken extends Model
{
    protected $fillable = [
        'user_id',
        'token',
        'platform',
        'device_label',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
        ];
    }

    public const PLATFORM_ANDROID = 'android';
    public const PLATFORM_IOS = 'ios';
    public const PLATFORM_WEB = 'web';

    public const PLATFORMS = [
        self::PLATFORM_ANDROID,
        self::PLATFORM_IOS,
        self::PLATFORM_WEB,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
