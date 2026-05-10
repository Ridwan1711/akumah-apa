<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TingkatSekolah extends Model
{
    public const CODE_MTS_7 = 'mts_7';

    public const CODE_MTS_8 = 'mts_8';

    public const CODE_MTS_9 = 'mts_9';

    public const CODE_MA_10 = 'ma_10';

    public const CODE_MA_11 = 'ma_11';

    public const CODE_MA_12 = 'ma_12';

    public const CODE_KULIAH = 'kuliah';

    /** @var array<string, string> */
    public const CODES = [
        self::CODE_MTS_7,
        self::CODE_MTS_8,
        self::CODE_MTS_9,
        self::CODE_MA_10,
        self::CODE_MA_11,
        self::CODE_MA_12,
        self::CODE_KULIAH,
    ];

    protected $fillable = [
        'name',
        'code',
        'group',
        'order',
        'is_billable',
    ];

    protected function casts(): array
    {
        return [
            'is_billable' => 'boolean',
            'order' => 'integer',
        ];
    }

    public function enrollmentTingkatSekolahs(): HasMany
    {
        return $this->hasMany(EnrollmentTingkatSekolah::class);
    }

    public function paymentTypeRules(): HasMany
    {
        return $this->hasMany(PaymentTypeTingkatSekolahRule::class);
    }
}
