<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class SaveTimeSlotsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'jam_count' => ['required', 'integer', 'between:1,20'],
            'day_count' => ['required', 'integer', 'between:1,7'],
            'slots' => ['required', 'array', 'min:1'],
            'slots.*.jam_no' => ['required', 'integer', 'min:1'],
            'slots.*.time_start' => ['required', 'date_format:H:i'],
            'slots.*.time_end' => ['required', 'date_format:H:i', 'after:slots.*.time_start'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $slots = $this->input('slots', []);
            $jamCount = (int) $this->input('jam_count', 0);
            $seen = [];
            foreach ($slots as $idx => $slot) {
                $jamNo = (int) ($slot['jam_no'] ?? 0);
                if ($jamNo < 1 || $jamNo > $jamCount) {
                    $v->errors()->add("slots.{$idx}.jam_no", "jam_no harus antara 1 dan {$jamCount}.");
                }
                if (isset($seen[$jamNo])) {
                    $v->errors()->add("slots.{$idx}.jam_no", "jam_no {$jamNo} duplikat.");
                }
                $seen[$jamNo] = true;
            }
        });
    }
}
