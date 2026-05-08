<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DormAssignment extends Model
{
    use Auditable, HasFactory;

    protected $fillable = ['student_id', 'room_id', 'academic_year_id', 'checkin_date', 'checkout_date'];

    protected function casts(): array
    {
        return [
            'checkin_date' => 'date',
            'checkout_date' => 'date',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('checkout_date');
    }

    public function scopeForAcademicYear(Builder $query, int $academicYearId): Builder
    {
        return $query->where('academic_year_id', $academicYearId);
    }

    public function scopeActiveInAcademicYear(Builder $query, int $academicYearId): Builder
    {
        return $query->forAcademicYear($academicYearId)->active();
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(DormRoom::class, 'room_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }
}
