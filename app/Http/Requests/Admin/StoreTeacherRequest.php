<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isAssignMode = $this->input('mode') === 'assign';

        return [
            'mode' => ['required', Rule::in(['create', 'assign'])],
            'existing_user_id' => ['nullable', 'required_if:mode,assign', 'integer', 'exists:users,id'],
            'name' => [Rule::excludeIf($isAssignMode), 'nullable', 'required_if:mode,create', 'string', 'max:255'],
            'username' => [Rule::excludeIf($isAssignMode), 'nullable', 'string', 'max:100', Rule::unique('users', 'username')],
            'email' => [Rule::excludeIf($isAssignMode), 'nullable', 'required_if:mode,create', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => [Rule::excludeIf($isAssignMode), 'nullable', 'required_if:mode,create', 'string', 'min:8'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
