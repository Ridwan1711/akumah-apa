<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLessonAttendanceRequest extends FormRequest
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
            'status' => ['required', 'in:present,excused,absent'],
            'reason' => ['nullable', 'string', 'max:255'],
            'leave_permission_id' => ['nullable', 'exists:leave_permissions,id'],
        ];
    }
}
