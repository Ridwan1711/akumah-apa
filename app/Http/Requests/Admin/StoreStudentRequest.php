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
            'nis' => ['required', 'string', 'max:20', 'unique:students,nis'],
            'full_name' => ['required', 'string', 'max:255'],
            'admission_year' => ['required', 'integer', 'min:2000', 'max:2099'],
        ];
    }
}
