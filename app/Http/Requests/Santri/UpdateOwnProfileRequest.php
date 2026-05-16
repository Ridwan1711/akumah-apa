<?php

namespace App\Http\Requests\Santri;

use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOwnProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'nik' => ['nullable', 'string', 'max:16'],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'gender' => ['required', Rule::in([Student::GENDER_MALE, Student::GENDER_FEMALE])],
            'address' => ['nullable', 'string'],
            'whatsapp_phone' => ['nullable', 'string', 'max:32'],
            'em_profile' => ['nullable', 'array'],
            'em_profile.nisn' => ['nullable', 'string', 'max:20'],
            'em_profile.nism' => ['nullable', 'string', 'max:50'],
            'em_profile.kewarganegaraan' => ['nullable', 'string', 'max:32'],
            'em_profile.agama' => ['nullable', 'string', 'max:64'],
            'em_profile.anak_ke' => ['nullable', 'string', 'max:16'],
            'em_profile.jumlah_saudara' => ['nullable', 'string', 'max:16'],
            'em_profile.no_hp' => ['nullable', 'string', 'max:32'],
            'em_profile.cita_cita' => ['nullable', 'string', 'max:255'],
            'em_profile.hobi' => ['nullable', 'string', 'max:255'],
            'em_profile.pendidikan_sebelumnya' => ['nullable', 'string', 'max:255'],
            'em_profile.status_mukim' => ['nullable', 'string', 'max:64'],
            'em_profile.status_tempat_tinggal' => ['nullable', 'string', 'max:128'],
            'em_profile.asal_daerah' => ['nullable', 'string', 'max:255'],
            'nis' => ['prohibited'],
            'admission_year' => ['prohibited'],
            'status' => ['prohibited'],
            'current_class_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'id' => ['prohibited'],
            'photo' => ['prohibited'],
        ];
    }
}
