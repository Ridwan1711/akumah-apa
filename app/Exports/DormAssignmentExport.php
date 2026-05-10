<?php

namespace App\Exports;

use App\Models\DormAssignment;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class DormAssignmentExport implements FromCollection, WithHeadings
{
    public function __construct(
        private readonly int $academicYearId,
    ) {}

    public function headings(): array
    {
        return [
            'academic_year_name',
            'nis',
            'student_name',
            'building_name',
            'room_number',
            'checkin_date',
            'checkout_date',
            'is_active',
        ];
    }

    public function collection()
    {
        return DormAssignment::query()
            ->where('academic_year_id', $this->academicYearId)
            ->with([
                'student:id,nis,full_name',
                'room.building:id,name',
                'academicYear:id,name',
            ])
            ->orderBy('checkin_date')
            ->orderBy('id')
            ->get()
            ->map(function (DormAssignment $a) {
                $active = $a->checkout_date === null;

                return [
                    'academic_year_name' => (string) ($a->academicYear?->name ?? ''),
                    'nis' => (string) ($a->student?->nis ?? ''),
                    'student_name' => (string) ($a->student?->full_name ?? ''),
                    'building_name' => (string) ($a->room?->building?->name ?? ''),
                    'room_number' => (string) ($a->room?->room_number ?? ''),
                    'checkin_date' => $a->checkin_date?->toDateString() ?? '',
                    'checkout_date' => $a->checkout_date?->toDateString() ?? '',
                    'is_active' => $active ? 'ya' : 'tidak',
                ];
            });
    }
}
