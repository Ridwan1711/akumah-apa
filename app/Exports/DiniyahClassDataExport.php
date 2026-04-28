<?php

namespace App\Exports;

use App\Models\Diniyyah\SchoolClass;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class DiniyahClassDataExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(
        protected array $filters = []
    ) {}

    public function query()
    {
        return SchoolClass::query()
            ->with('gradeLevel:id,name')
            ->when($this->filters['search'] ?? null, function ($query, $search) {
                $query->where('name', 'ilike', "%{$search}%");
            })
            ->orderBy('order')
            ->orderBy('name');
    }

    public function headings(): array
    {
        return [
            'name',
            'grade_level_id',
            'grade_level_name',
            'order',
            'student_gender',
        ];
    }

    public function map($row): array
    {
        return [
            $row->name,
            $row->grade_level_id,
            $row->gradeLevel?->name,
            $row->order,
            $row->student_gender,
        ];
    }
}
