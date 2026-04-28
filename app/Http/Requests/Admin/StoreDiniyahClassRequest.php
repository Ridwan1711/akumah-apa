<?php

namespace App\Http\Requests\Admin;

use App\Models\Diniyyah\SchoolClass;
use Illuminate\Foundation\Http\FormRequest;

class StoreDiniyahClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge([
            'name' => ['required', 'string', 'max:100'],
            'grade_level_id' => ['required', 'exists:grade_levels,id'],
            'order' => ['required', 'integer', 'min:0', 'max:1000'],
        ], SchoolClass::studentGenderValidationRules(required: true));
    }
}
