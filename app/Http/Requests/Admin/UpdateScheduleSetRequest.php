<?php

namespace App\Http\Requests\Admin;

use App\Models\Diniyyah\AcademicSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateScheduleSetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $set = $this->route('scheduleSet') ?? $this->route('schedule_set');
        $setId = is_object($set) ? $set->id : null;
        $periodId = is_object($set) ? $set->period_id : $this->input('period_id');

        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('schedule_sets', 'name')
                    ->where(fn ($q) => $q->where('period_id', $periodId))
                    ->ignore($setId),
            ],
            'jam_count' => ['nullable', 'integer', 'between:1,20'],
            'day_count' => ['nullable', 'integer', 'between:1,'.AcademicSchedule::maxMatrixDayCount()],
        ];
    }
}
