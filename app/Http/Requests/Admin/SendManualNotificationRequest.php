<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendManualNotificationRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:1000'],
            'deeplink' => ['nullable', 'string', 'max:255'],
            'target_mode' => ['required', Rule::in(['single_user', 'multi_user', 'specific_devices'])],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'device_token_ids' => ['nullable', 'array'],
            'device_token_ids.*' => ['integer', 'exists:device_tokens,id'],
        ];
    }

    public function after(): array
    {
        return [
            function (\Illuminate\Validation\Validator $validator): void {
                $mode = (string) $this->input('target_mode');
                $userIds = collect($this->input('user_ids', []))
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $id > 0)
                    ->unique()
                    ->values();
                $deviceTokenIds = collect($this->input('device_token_ids', []))
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $id > 0)
                    ->unique()
                    ->values();

                if ($mode === 'single_user' && $userIds->count() !== 1) {
                    $validator->errors()->add('user_ids', 'Mode single user wajib memilih tepat 1 user.');
                }

                if ($mode === 'multi_user' && $userIds->isEmpty()) {
                    $validator->errors()->add('user_ids', 'Mode multi user wajib memilih minimal 1 user.');
                }

                if ($mode === 'specific_devices' && $deviceTokenIds->isEmpty()) {
                    $validator->errors()->add('device_token_ids', 'Mode device spesifik wajib memilih minimal 1 device.');
                }
            },
        ];
    }
}
