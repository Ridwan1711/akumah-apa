<?php

namespace App\Http\Requests\Admin;

use App\Models\Diniyyah\Subject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKitabSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $subject = $this->route('kitab_subject');
        $ignoreId = $subject instanceof Subject ? $subject->id : null;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('subjects', 'name')->ignore($ignoreId),
            ],
            'fan_id' => ['nullable', 'exists:fans,id'],
        ];
    }
}
