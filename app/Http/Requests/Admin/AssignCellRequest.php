<?php

namespace App\Http\Requests\Admin;

use App\Models\Diniyyah\TeacherAssignment;
use App\Services\Schedule\ScheduleMatrixService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignCellRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pengampu_id' => ['required', 'exists:teacher_assignments,id'],
            'day' => ['required', 'integer', 'between:1,7'],
            'jam_no' => ['required', 'integer', 'min:1'],
            'action' => [
                'required',
                Rule::in([
                    ScheduleMatrixService::ACTION_ASSIGN,
                    ScheduleMatrixService::ACTION_MERGE,
                    ScheduleMatrixService::ACTION_REPLACE,
                    ScheduleMatrixService::ACTION_REPLACE_CELL,
                ]),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $set = $this->route('scheduleSet') ?? $this->route('schedule_set');
            if (! $set) {
                return;
            }

            $pengampuId = (int) $this->input('pengampu_id');
            $pengampu = TeacherAssignment::find($pengampuId);
            if (! $pengampu) {
                return;
            }

            if ((int) $pengampu->period_id !== (int) $set->period_id) {
                $v->errors()->add('pengampu_id', 'Pengampu tidak berada pada periode yang sama dengan schedule set.');
            }

            $jamNo = (int) $this->input('jam_no');
            if ($jamNo > (int) $set->jam_count) {
                $v->errors()->add('jam_no', 'jam_no melebihi jam_count schedule set.');
            }

            $day = (int) $this->input('day');
            if ($day > (int) $set->day_count) {
                $v->errors()->add('day', 'day melebihi day_count schedule set.');
            }
        });
    }
}
