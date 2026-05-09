<?php

namespace App\Http\Requests\Admin;

use App\Models\Diniyyah\AcademicSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreScheduleSetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $semesterId = (int) $this->input('semester_id');
        $periodId = \Illuminate\Support\Facades\DB::table('academic_periods')
            ->where('semester_id', $semesterId)
            ->value('id');

        return [
            'semester_id' => ['required', 'exists:semesters,id'],
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('schedule_sets', 'name')->where(fn ($q) => $q->where('period_id', $periodId)),
            ],
            'jam_count' => ['nullable', 'integer', 'between:1,20'],
            'day_count' => ['nullable', 'integer', 'between:1,'.AcademicSchedule::maxMatrixDayCount()],
            'is_active' => ['nullable', 'boolean'],
            'copy_from_id' => ['nullable', 'exists:schedule_sets,id'],
        ];
    }
}
