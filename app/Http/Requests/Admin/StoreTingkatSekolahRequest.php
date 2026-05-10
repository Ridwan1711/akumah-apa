<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTingkatSekolahRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:48', Rule::unique('tingkat_sekolahs', 'code')->whereNotNull('code')],
            'group' => ['nullable', 'string', 'max:48'],
            'order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_billable' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array{name: string, code: ?string, group: ?string, order: int, is_billable: bool}
     */
    public function tingkatPayload(): array
    {
        $code = trim((string) ($this->input('code') ?? ''));
        $group = trim((string) ($this->input('group') ?? ''));

        return [
            'name' => trim((string) $this->input('name')),
            'code' => $code === '' ? null : $code,
            'group' => $group === '' ? null : $group,
            'order' => (int) ($this->input('order') ?? 0),
            'is_billable' => $this->boolean('is_billable'),
        ];
    }
}
