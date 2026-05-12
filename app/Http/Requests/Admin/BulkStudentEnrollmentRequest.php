<?php

namespace App\Http\Requests\Admin;

use App\Services\Diniyyah\StudentEnrollmentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkStudentEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('mode') === StudentEnrollmentService::MODE_CLEAR) {
            $this->merge(['class_id' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::in([
                StudentEnrollmentService::MODE_ASSIGN,
                StudentEnrollmentService::MODE_MOVE,
                StudentEnrollmentService::MODE_CLEAR,
            ])],
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer', 'exists:students,id'],
            'period_id' => ['required', 'integer', 'exists:academic_periods,id'],
            'class_id' => ['nullable', 'exists:classes,id', 'required_unless:mode,clear'],
        ];
    }
}

