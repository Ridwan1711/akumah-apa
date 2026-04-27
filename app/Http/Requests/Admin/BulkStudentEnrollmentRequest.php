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
            'semester_id' => ['required', 'exists:semesters,id'],
            'class_id' => ['nullable', 'exists:classes,id', 'required_unless:mode,clear'],
        ];
    }
}

