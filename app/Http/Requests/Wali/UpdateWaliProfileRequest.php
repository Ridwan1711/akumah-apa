<?php

namespace App\Http\Requests\Wali;

use App\Models\Guardian;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWaliProfileRequest extends FormRequest
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
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'income_band' => ['nullable', 'string', 'max:100'],
            'relationship' => ['required', Rule::in(Guardian::RELATIONSHIPS)],
        ];
    }
}
