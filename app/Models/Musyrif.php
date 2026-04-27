<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Musyrif extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'assigned_room_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedRoom(): BelongsTo
    {
        return $this->belongsTo(DormRoom::class, 'assigned_room_id');
    }
}
