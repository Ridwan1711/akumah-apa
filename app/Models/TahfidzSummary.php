<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TahfidzSummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'total_juz_completed',
        'last_hafalan_date',
    ];

    protected function casts(): array
    {
        return [
            'last_hafalan_date' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
