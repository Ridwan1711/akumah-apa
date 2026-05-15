<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentFormalContinuationRequest extends Model
{
    use Auditable;

    public const CHOICE_MA_10 = 'ma_10';

    public const CHOICE_KULIAH = 'kuliah';

    public const STATUS_AWAITING = 'awaiting_confirmations';

    public const STATUS_PENDING_ADMIN = 'pending_admin';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const OPEN_STATUSES = [
        self::STATUS_AWAITING,
        self::STATUS_PENDING_ADMIN,
    ];

    protected $fillable = [
        'formal_continuation_round_id',
        'student_id',
        'source_academic_year_id',
        'target_academic_year_id',
        'current_tingkat_sekolah_id',
        'current_tingkat_code',
        'status',
        'initiated_by',
        'initiated_by_user_id',
        'santri_choice',
        'santri_user_id',
        'santri_confirmed_at',
        'wali_choice',
        'wali_user_id',
        'wali_guardian_id',
        'wali_confirmed_at',
        'resolved_choice',
        'notes',
        'reviewed_by',
        'reviewed_at',
        'admin_notes',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'santri_confirmed_at' => 'datetime',
            'wali_confirmed_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(FormalContinuationRound::class, 'formal_continuation_round_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function currentTingkatSekolah(): BelongsTo
    {
        return $this->belongsTo(TingkatSekolah::class, 'current_tingkat_sekolah_id');
    }

    public function sourceAcademicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'source_academic_year_id');
    }

    public function targetAcademicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'target_academic_year_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function bothChoicesSubmitted(): bool
    {
        return $this->santri_choice !== null && $this->wali_choice !== null;
    }

    /**
     * @return list<string>
     */
    public static function allowedChoicesForCode(string $tingkatCode): array
    {
        return match ($tingkatCode) {
            TingkatSekolah::CODE_MTS_9 => [self::CHOICE_MA_10, self::CHOICE_KULIAH],
            TingkatSekolah::CODE_MA_12 => [self::CHOICE_KULIAH],
            default => [],
        };
    }

    public static function choiceLabel(string $choice): string
    {
        return match ($choice) {
            self::CHOICE_MA_10 => 'Lanjut MA 10',
            self::CHOICE_KULIAH => 'Tidak lanjut MA — tingkat Kuliah (tetap santri)',
            default => $choice,
        };
    }
}
