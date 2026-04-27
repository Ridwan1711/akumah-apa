<?php

namespace App\Http\Requests\Admin;

use App\Models\Diniyyah\SchoolClass;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDiniyahClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $level = $this->input('level');
        if ($level === '' || $level === null) {
            $this->merge(['level' => null]);
        }
    }

    public function rules(): array
    {
        return array_merge([
            'name' => ['required', 'string', 'max:100'],
            'grade_level_id' => ['required', 'exists:grade_levels,id'],
            'level_order' => ['required', 'integer', 'min:0', 'max:1000'],
            'level' => ['nullable', Rule::in(SchoolClass::LEVELS)],
        ], SchoolClass::studentGenderValidationRules(required: true));
    }
}
