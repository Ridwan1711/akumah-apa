<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaSendLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'phone_hash',
        'tag',
        'status',
        'error',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
