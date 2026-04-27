<?php

namespace App\Http\Requests\Santri;

use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOwnProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'nik' => ['nullable', 'string', 'max:16'],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'gender' => ['required', Rule::in([Student::GENDER_MALE, Student::GENDER_FEMALE])],
            'address' => ['nullable', 'string'],
            'whatsapp_phone' => ['nullable', 'string', 'max:32'],
            'google_connected' => ['nullable', 'boolean'],
            'nis' => ['prohibited'],
            'admission_year' => ['prohibited'],
            'status' => ['prohibited'],
            'current_class_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'id' => ['prohibited'],
            'photo' => ['prohibited'],
        ];
    }
}
