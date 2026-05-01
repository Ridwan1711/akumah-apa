<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'valid_from' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after_or_equal:valid_from'],
            'principal_name' => ['required', 'string', 'max:255'],
            'principal_title' => ['nullable', 'string', 'max:255'],
            'stamp' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
