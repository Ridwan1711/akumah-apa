<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentViolation extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'student_id',
        'violation_type_id',
        'date',
        'description',
        'handled_by',
        'status',
        'resolution_notes',
    ];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public const STATUS_OPEN = 'open';
    public const STATUS_RESOLVED = 'resolved';

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function violationType(): BelongsTo
    {
        return $this->belongsTo(ViolationType::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
