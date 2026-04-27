<?php

namespace App\Models\Diniyyah;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleTimeSlot extends Model
{
    protected $fillable = [
        'schedule_set_id',
        'jam_no',
        'time_start',
        'time_end',
    ];

    protected function casts(): array
    {
        return [
            'jam_no' => 'integer',
        ];
    }

    public function scheduleSet(): BelongsTo
    {
        return $this->belongsTo(ScheduleSet::class, 'schedule_set_id');
    }
}
