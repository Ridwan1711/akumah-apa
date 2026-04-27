<?php

namespace App\Models\Diniyyah;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassWali extends Model
{
    protected $table = 'class_walis';

    protected $fillable = [
        'class_id',
        'teacher_id',
        'period_id',
    ];

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(\App\Models\AcademicPeriod::class, 'period_id');
    }
}
