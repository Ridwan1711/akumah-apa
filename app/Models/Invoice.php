<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'invoice_number',
        'student_id',
        'tingkat_sekolah_id',
        'payment_type_id',
        'academic_year_id',
        'semester_id',
        'month',
        'amount',
        'discount_amount',
        'final_amount',
        'breakdown',
        'status',
        'due_date',
        'notes',
        'generated_by',
        'last_reminder_sent_at',
        'reminder_count',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'final_amount' => 'decimal:2',
            'breakdown' => 'array',
            'due_date' => 'date',
            'month' => 'integer',
            'last_reminder_sent_at' => 'datetime',
            'reminder_count' => 'integer',
        ];
    }

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_OVERDUE = 'overdue';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PAID,
        self::STATUS_PARTIAL,
        self::STATUS_OVERDUE,
        self::STATUS_CANCELLED,
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function tingkatSekolah(): BelongsTo
    {
        return $this->belongsTo(TingkatSekolah::class);
    }

    public function paymentType(): BelongsTo
    {
        return $this->belongsTo(PaymentType::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Tagihan milik tingkat formal: snapshot kolom tingkat atau enrollment tahun ajaran tagihan yang sama.
     */
    public function scopeForFormalTingkat(Builder $query, int $tingkatSekolahId): Builder
    {
        return $query->where(function (Builder $w) use ($tingkatSekolahId): void {
            $w->where('invoices.tingkat_sekolah_id', $tingkatSekolahId)
                ->orWhere(function (Builder $w2) use ($tingkatSekolahId): void {
                    $w2->whereNull('invoices.tingkat_sekolah_id')
                        ->whereExists(function ($sub) use ($tingkatSekolahId): void {
                            $sub->selectRaw('1')
                                ->from('enrollment_tingkat_sekolahs as ets')
                                ->whereColumn('ets.student_id', 'invoices.student_id')
                                ->whereColumn('ets.academic_year_id', 'invoices.academic_year_id')
                                ->where('ets.tingkat_sekolah_id', $tingkatSekolahId);
                        });
                });
        });
    }

    public function totalPaid(): float
    {
        return (float) $this->payments()
            ->where('status', Payment::STATUS_VERIFIED)
            ->sum('amount');
    }

    public function remainingAmount(): float
    {
        return max(0, (float) $this->final_amount - $this->totalPaid());
    }

    public function pendingAmount(): float
    {
        return (float) $this->payments()
            ->where('status', Payment::STATUS_PENDING)
            ->sum('amount');
    }

    public function effectiveRemaining(): float
    {
        return max(0, $this->remainingAmount() - $this->pendingAmount());
    }

    /**
     * @return array<int, array{label:string, amount:float}>
     */
    public function resolvedBreakdown(): array
    {
        $invoiceBreakdown = PaymentType::normalizeBreakdownItems($this->breakdown);
        if ($invoiceBreakdown !== []) {
            return $invoiceBreakdown;
        }

        if (! $this->relationLoaded('paymentType') || ! $this->paymentType) {
            $this->loadMissing('paymentType');
        }

        return $this->paymentType
            ? $this->paymentType->buildBreakdownForAmount((float) $this->amount)
            : [];
    }

    public function recalculateStatus(): void
    {
        if ($this->status === self::STATUS_CANCELLED) {
            return;
        }

        $paid = $this->totalPaid();

        if ($paid >= (float) $this->final_amount) {
            $this->status = self::STATUS_PAID;
        } elseif ($paid > 0) {
            $this->status = self::STATUS_PARTIAL;
        } elseif ($this->due_date->isPast()) {
            $this->status = self::STATUS_OVERDUE;
        } else {
            $this->status = self::STATUS_PENDING;
        }

        $this->saveQuietly();
    }

    public static function generateNumber(
        mixed $type = null,
        mixed $month = null,
        mixed $academicYearId = null,
        mixed $santriName = null
    ): string
    {
        // Backward-compatible:
        // - tanpa argumen => format lama berbasis YYYYMM
        // - dengan argumen => format custom bulk (type/month/academicYear/santriName)
        if ($type === null && $month === null && $academicYearId === null && $santriName === null) {
            $prefix = 'INV-' . now()->format('Ym') . '-';
        } else {
            $santriPart = (string) ($santriName ?? 'santri');
            $santriPart = trim($santriPart) === '' ? 'santri' : trim($santriPart);
            $santriPart = preg_replace('/[^A-Za-z0-9]+/', '_', $santriPart) ?? 'santri';
            $santriPart = trim($santriPart, '_');
            $santriPart = $santriPart === '' ? 'santri' : $santriPart;

            $typePart = (string) ($type ?? 'GEN');
            $yearPart = (string) ($academicYearId ?? '0');
            if ($month === null || $month === '') {
                $prefix = $typePart . '-' . $yearPart . '-' . $santriPart . '-';
            } else {
                $prefix = $typePart . '-' . (string) $month . '-' . $yearPart . '-' . $santriPart . '-';
            }
        }
        $last = static::where('invoice_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('invoice_number');

        $lastSeq = 0;
        if (is_string($last) && str_starts_with($last, $prefix)) {
            $suffix = substr($last, strlen($prefix));
            $lastSeq = ctype_digit($suffix) ? (int) $suffix : 0;
        }
        $seq = $lastSeq + 1;

        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
