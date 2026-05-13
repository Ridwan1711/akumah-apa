<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BulkSetTeacherActiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'teacher_ids' => ['required', 'array', 'min:1', 'max:100'],
            'teacher_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
