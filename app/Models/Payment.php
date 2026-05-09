<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Payment extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'payment_number',
        'invoice_id',
        'amount',
        'payment_method',
        'payment_date',
        'proof_file',
        'gateway_order_id',
        'gateway_transaction_id',
        'gateway_payment_type',
        'gateway_va_number',
        'gateway_qr_url',
        'gateway_expiry_time',
        'status',
        'verified_by',
        'verified_at',
        'notes',
    ];

    protected $appends = [
        'proof_url',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'date',
            'verified_at' => 'datetime',
            'gateway_expiry_time' => 'datetime',
        ];
    }

    /**
     * URL publik bukti transfer (jika ada). Otomatis ikut serialisasi JSON
     * supaya klien Flutter/Inertia bisa langsung memuat preview.
     */
    protected function proofUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            $path = $this->proof_file;
            if (! is_string($path) || trim($path) === '') {
                return null;
            }

            if (preg_match('#^https?://#i', $path) === 1) {
                return $path;
            }

            return Storage::disk('public')->url($path);
        });
    }

    public const STATUS_PENDING = 'pending';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_VERIFIED,
        self::STATUS_REJECTED,
    ];

    public const METHOD_CASH = 'cash';
    public const METHOD_BANK_TRANSFER = 'bank_transfer';
    public const METHOD_GATEWAY = 'gateway';

    public const METHODS = [
        self::METHOD_CASH,
        self::METHOD_BANK_TRANSFER,
        self::METHOD_GATEWAY,
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public static function generateNumber(): string
    {
        $prefix = 'PAY-' . now()->format('Ymd') . '-';
        $last = static::where('payment_number', 'like', $prefix . '%')
            ->orderByDesc('payment_number')
            ->value('payment_number');

        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
