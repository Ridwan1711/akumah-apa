<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id', 'unique:students,user_id'],
            'full_name' => ['required', 'string', 'max:255'],
            'admission_year' => ['required', 'integer', 'min:2000', 'max:2099'],
        ];
    }
}
