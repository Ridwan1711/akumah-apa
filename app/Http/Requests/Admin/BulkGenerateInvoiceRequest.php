<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkGenerateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string|\Illuminate\Contracts\Validation\ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'payment_type_id' => ['required', 'exists:payment_types,id'],
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'target_type' => ['required', Rule::in(['all', 'selected'])],
            'student_ids' => ['exclude_unless:target_type,selected', 'required', 'array', 'min:1'],
            'student_ids.*' => ['exclude_unless:target_type,selected', 'integer', 'exists:students,id'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'due_date' => ['required', 'date'],
            'send_notification_for_existing' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('send_notification_for_existing')) {
            $this->merge([
                'send_notification_for_existing' => filter_var(
                    $this->input('send_notification_for_existing'),
                    FILTER_VALIDATE_BOOLEAN
                ),
            ]);
        }
    }
}
