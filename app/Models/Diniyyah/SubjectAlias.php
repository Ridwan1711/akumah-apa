<?php

namespace App\Models\Diniyyah;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubjectAlias extends Model
{
    protected $fillable = [
        'subject_id',
        'tingkat_id',
        'alias_name',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function tingkat(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class, 'tingkat_id');
    }
}
