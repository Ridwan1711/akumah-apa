<?php

namespace App\Models\Diniyyah;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fan extends Model
{
    protected $fillable = [
        'name',
        'description',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class, 'fan_id');
    }

}
