<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PermissionScope extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'permission_name',
        'scope_key',
        'scope_value',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

