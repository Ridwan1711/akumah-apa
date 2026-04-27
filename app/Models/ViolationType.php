<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ViolationType extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'points', 'category'];

    public const CATEGORY_RINGAN = 'ringan';
    public const CATEGORY_SEDANG = 'sedang';
    public const CATEGORY_BERAT = 'berat';

    public const CATEGORIES = [
        self::CATEGORY_RINGAN,
        self::CATEGORY_SEDANG,
        self::CATEGORY_BERAT,
    ];

    public function violations(): HasMany
    {
        return $this->hasMany(StudentViolation::class);
    }
}
