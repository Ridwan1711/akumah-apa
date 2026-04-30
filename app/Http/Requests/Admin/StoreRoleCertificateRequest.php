<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'certificate_type' => ['required', Rule::in(['teacher', 'student_position'])],
            'user_id' => ['nullable', 'required_if:certificate_type,teacher', 'exists:users,id'],
            'student_position_id' => ['nullable', 'required_if:certificate_type,student_position', 'exists:student_positions,id'],
            'academic_period_id' => ['nullable', 'exists:academic_periods,id'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'notes' => ['nullable', 'string', 'max:500'],
            'action' => ['nullable', Rule::in(['issue', 'reissue'])],
        ];
    }
}
