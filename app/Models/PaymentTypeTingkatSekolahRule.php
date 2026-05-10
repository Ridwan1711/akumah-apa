<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTypeTingkatSekolahRule extends Model
{
    protected $fillable = [
        'payment_type_id',
        'tingkat_sekolah_id',
        'is_enabled',
        'amount',
        'breakdown',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'amount' => 'decimal:2',
            'breakdown' => 'array',
        ];
    }

    public function paymentType(): BelongsTo
    {
        return $this->belongsTo(PaymentType::class);
    }

    public function tingkatSekolah(): BelongsTo
    {
        return $this->belongsTo(TingkatSekolah::class);
    }

    /**
     * @return array<int, array{label:string, amount:float}>
     */
    public function normalizedBreakdown(): array
    {
        return PaymentType::normalizeBreakdownItems($this->breakdown);
    }
}
