<?php

namespace App\Http\Requests\Admin;

use App\Models\AcademicPeriod;
use App\Models\Diniyyah\SchoolClass;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreDiniyahClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('wali_teacher_id') && $this->input('wali_teacher_id') === '') {
            $this->merge(['wali_teacher_id' => null]);
        }
    }

    public function rules(): array
    {
        return array_merge([
            'name' => ['required', 'string', 'max:100'],
            'grade_level_id' => ['required', 'exists:grade_levels,id'],
            'order' => ['required', 'integer', 'min:0', 'max:1000'],
            'wali_teacher_id' => ['nullable', 'integer', 'exists:users,id'],
        ], SchoolClass::studentGenderValidationRules(required: true));
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if ($this->filled('wali_teacher_id') && ! AcademicPeriod::query()->active()->exists()) {
                $v->errors()->add(
                    'wali_teacher_id',
                    'Belum ada periode akademik aktif; wali kelas tidak dapat ditetapkan.',
                );
            }

            if (! $this->filled('wali_teacher_id')) {
                return;
            }

            $teacher = User::query()
                ->whereKey((int) $this->input('wali_teacher_id'))
                ->where('is_active', true)
                ->whereHas('roles', fn ($q) => $q->where('name', Role::GURU))
                ->exists();

            if (! $teacher) {
                $v->errors()->add('wali_teacher_id', 'Guru wali kelas harus pengguna aktif dengan peran guru.');
            }
        });
    }
}
