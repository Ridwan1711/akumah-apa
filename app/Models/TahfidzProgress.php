<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TahfidzProgress extends Model
{
    use Auditable, HasFactory;

    protected $table = 'tahfidz_progress';

    protected $fillable = [
        'student_id',
        'juz',
        'surah_from',
        'surah_to',
        'ayat_from',
        'ayat_to',
        'type',
        'grade',
        'notes',
        'validated_by',
        'validated_at',
    ];

    protected function casts(): array
    {
        return [
            'validated_at' => 'datetime',
        ];
    }

    public const TYPE_ZIYADAH = 'ziyadah';
    public const TYPE_MUROJAAH = 'murojaah';
    public const TYPES = [self::TYPE_ZIYADAH, self::TYPE_MUROJAAH];

    public const GRADE_MUMTAZ = 'mumtaz';
    public const GRADE_JAYYID_JIDDAN = 'jayyid_jiddan';
    public const GRADE_JAYYID = 'jayyid';
    public const GRADE_MAQBUL = 'maqbul';
    public const GRADE_RASIB = 'rasib';

    public const GRADES = [
        self::GRADE_MUMTAZ,
        self::GRADE_JAYYID_JIDDAN,
        self::GRADE_JAYYID,
        self::GRADE_MAQBUL,
        self::GRADE_RASIB,
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }
}
