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
        'default_breakdown',
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
            'default_breakdown' => 'array',
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

    /**
     * @return array<int, array{label:string, amount:float}>
     */
    public function normalizedDefaultBreakdown(): array
    {
        return self::normalizeBreakdownItems($this->default_breakdown);
    }

    /**
     * @param  mixed  $items
     * @return array<int, array{label:string, amount:float}>
     */
    public static function normalizeBreakdownItems(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $normalized = collect($items)
            ->map(function ($item): ?array {
                if (! is_array($item)) {
                    return null;
                }

                $label = trim((string) ($item['label'] ?? ''));
                $amount = (float) ($item['amount'] ?? 0);

                if ($label === '' || $amount <= 0) {
                    return null;
                }

                return [
                    'label' => $label,
                    'amount' => round($amount, 2),
                ];
            })
            ->filter()
            ->values()
            ->all();

        return $normalized;
    }

    /**
     * @param  array<int, array{label:string, amount:float}>  $items
     */
    public static function breakdownTotal(array $items): float
    {
        return round((float) collect($items)->sum('amount'), 2);
    }

    /**
     * @return array<int, array{label:string, amount:float}>
     */
    public function buildBreakdownForAmount(float $targetAmount): array
    {
        $items = $this->normalizedDefaultBreakdown();
        if ($items === []) {
            return [];
        }

        $targetAmount = round(max(0, $targetAmount), 2);
        $templateTotal = self::breakdownTotal($items);
        if ($templateTotal <= 0 || $targetAmount <= 0) {
            return [];
        }

        if (abs($templateTotal - $targetAmount) < 0.01) {
            return $items;
        }

        $ratio = $targetAmount / $templateTotal;
        $adjusted = [];
        $allocated = 0.0;

        foreach ($items as $index => $item) {
            $isLast = $index === count($items) - 1;
            $amount = $isLast
                ? round($targetAmount - $allocated, 2)
                : round($item['amount'] * $ratio, 2);

            $amount = max(0, $amount);
            $allocated += $amount;
            $adjusted[] = [
                'label' => $item['label'],
                'amount' => $amount,
            ];
        }

        return self::normalizeBreakdownItems($adjusted);
    }
}
