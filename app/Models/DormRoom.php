<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DormRoom extends Model
{
    use HasFactory;

    protected $fillable = ['building_id', 'room_number', 'capacity', 'floor'];

    public function building(): BelongsTo
    {
        return $this->belongsTo(DormBuilding::class, 'building_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DormAssignment::class, 'room_id');
    }

    public function activeAssignments(): HasMany
    {
        return $this->assignments()->whereNull('checkout_date');
    }

    public function musyrif(): HasOne
    {
        return $this->hasOne(Musyrif::class, 'assigned_room_id');
    }
}
