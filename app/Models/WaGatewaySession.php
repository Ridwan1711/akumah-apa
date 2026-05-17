<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaGatewaySession extends Model
{
    public const STATUS_DISCONNECTED = 'disconnected';

    public const STATUS_PAIRING = 'pairing';

    public const STATUS_READY = 'ready';

    public const STATUS_AUTH_FAILURE = 'auth_failure';

    protected $fillable = [
        'slug',
        'label',
        'description',
        'status',
        'linked_phone',
        'qr_data_url',
        'qr_updated_at',
        'last_ready_at',
        'last_error',
        'is_enabled',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'qr_updated_at' => 'datetime',
            'last_ready_at' => 'datetime',
            'is_enabled' => 'boolean',
        ];
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }
}
