<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentType extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'name',
        'code',
        'category',
        'is_recurring',
        'default_amount',
        'kuliah_amount',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_recurring' => 'boolean',
            'is_active' => 'boolean',
            'default_amount' => 'decimal:2',
            'kuliah_amount' => 'decimal:2',
        ];
    }

    public const CATEGORY_SPP = 'spp';
    public const CATEGORY_NON_SPP = 'non_spp';
    public const CATEGORY_INFAQ = 'infaq';

    public const CATEGORIES = [
        self::CATEGORY_SPP,
        self::CATEGORY_NON_SPP,
        self::CATEGORY_INFAQ,
    ];

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function studentDiscounts(): HasMany
    {
        return $this->hasMany(StudentDiscount::class);
    }
}
