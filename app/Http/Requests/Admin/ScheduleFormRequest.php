<?php

namespace App\Http\Requests\Admin;

use App\Models\Diniyyah\AcademicSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScheduleFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return self::scheduleRules();
    }

    /**
     * @return array<string, mixed>
     */
    public static function scheduleRules(): array
    {
        return [
            'class_id' => ['required', 'exists:classes,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'teacher_id' => ['required', 'exists:users,id'],
            'semester_id' => ['required', 'exists:semesters,id'],
            'day' => ['required', 'integer', Rule::in(AcademicSchedule::TEACHING_DAYS)],
            'time_start' => ['required', 'date_format:H:i'],
            'time_end' => ['required', 'date_format:H:i', 'after:time_start'],
        ];
    }
}
