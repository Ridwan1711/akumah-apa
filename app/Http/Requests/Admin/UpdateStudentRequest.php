<?php

namespace App\Http\Requests\Admin;

use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id', Rule::unique('students', 'user_id')->ignore($this->route('student'))],
            'nis' => ['required', 'string', 'max:20', Rule::unique('students', 'nis')->ignore($this->route('student'))],
            'nik' => ['nullable', 'string', 'max:16'],
            'full_name' => ['required', 'string', 'max:255'],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'gender' => ['required', Rule::in([Student::GENDER_MALE, Student::GENDER_FEMALE])],
            'address' => ['nullable', 'string'],
            'status' => ['required', Rule::in(Student::STATUSES)],
            'admission_year' => ['required', 'integer', 'min:2000', 'max:2099'],
            'current_class_id' => ['nullable', 'exists:classes,id'],
        ];
    }
}
