<?php

namespace App\Support;

use Illuminate\Validation\Rule;

class GuardianProfileRules
{
    public const WALI_SOURCES = ['manual', 'ayah', 'ibu'];

    public const STATUSES = ['hidup', 'wafat'];

    /**
     * @return array<string, mixed>
     */
    public static function profileUpdateRules(): array
    {
        $guardianFieldRules = static::guardianFieldRules();

        return array_merge([
            'em_profile' => ['nullable', 'array'],
            'full_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'nik' => ['sometimes', 'nullable', 'string', 'max:32'],
            'birth_place' => ['sometimes', 'nullable', 'string', 'max:120'],
            'birth_date' => ['sometimes', 'nullable', 'date'],
            'gender' => ['sometimes', 'nullable', 'in:L,P'],
            'address' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'parents' => ['sometimes', 'array'],
            'parents.wali_data_source' => ['nullable', Rule::in(self::WALI_SOURCES)],
            'parents.ayah' => ['sometimes', 'array'],
            'parents.ibu' => ['sometimes', 'array'],
            'parents.wali' => ['nullable', 'array'],
            'parents.ayah.full_name' => ['required_with:parents', 'string', 'max:255'],
            'parents.ibu.full_name' => ['required_with:parents', 'string', 'max:255'],
            'parents.wali.full_name' => ['required_if:parents.wali_data_source,manual', 'nullable', 'string', 'max:255'],
            'addresses' => ['sometimes', 'array'],
            'addresses.ayah' => ['sometimes', 'array'],
            'addresses.ibu' => ['sometimes', 'array'],
            'addresses.wali' => ['sometimes', 'array'],
            'addresses.santri' => ['sometimes', 'array'],
            'addresses.ibu_sama_dengan_ayah' => ['nullable', 'boolean'],
            'addresses.wali_sama_dengan_ayah' => ['nullable', 'boolean'],
            'addresses.wali_sama_dengan_ibu' => ['nullable', 'boolean'],
        ], $guardianFieldRules);
    }

    /**
     * @return array<string, mixed>
     */
    private static function guardianFieldRules(): array
    {
        $rules = [];
        foreach (['ayah', 'ibu', 'wali'] as $role) {
            $prefix = "parents.{$role}";
            $rules["{$prefix}.status"] = ['nullable', Rule::in(self::STATUSES)];
            $rules["{$prefix}.kewarganegaraan"] = ['nullable', 'string', 'max:32'];
            $rules["{$prefix}.nik"] = ['nullable', 'string', 'max:32'];
            $rules["{$prefix}.birth_place"] = ['nullable', 'string', 'max:120'];
            $rules["{$prefix}.birth_date"] = ['nullable', 'date'];
            $rules["{$prefix}.last_education"] = ['nullable', 'string', 'max:120'];
            $rules["{$prefix}.occupation"] = ['nullable', 'string', 'max:120'];
            $rules["{$prefix}.monthly_income"] = ['nullable', 'string', 'max:64'];
            $rules["{$prefix}.income_band"] = ['nullable', 'string', 'max:64'];
            $rules["{$prefix}.phone"] = ['nullable', 'string', 'max:32'];
            $rules["{$prefix}.without_phone"] = ['nullable', 'boolean'];
            $rules["{$prefix}.email"] = ['nullable', 'email', 'max:255'];
            $rules["{$prefix}.no_kks"] = ['nullable', 'string', 'max:64'];
            $rules["{$prefix}.no_pkh"] = ['nullable', 'string', 'max:64'];
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function sanitizeDeceasedGuardian(array $data): array
    {
        $status = $data['status'] ?? null;
        if ($status !== 'wafat') {
            return $data;
        }

        return [
            'full_name' => $data['full_name'] ?? null,
            'status' => 'wafat',
        ];
    }
}
