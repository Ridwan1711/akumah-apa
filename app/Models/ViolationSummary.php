<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ViolationSummary extends Model
{
    protected $fillable = ['student_id', 'total_points', 'last_violation_date'];

    protected function casts(): array
    {
        return ['last_violation_date' => 'date'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
