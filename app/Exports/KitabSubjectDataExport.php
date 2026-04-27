<?php

namespace App\Exports;

use App\Models\Diniyyah\Subject;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class KitabSubjectDataExport implements FromQuery, WithHeadings, WithMapping
{
    public function query()
    {
        return Subject::query()->orderBy('name');
    }

    public function headings(): array
    {
        return [
            'name',
        ];
    }

    public function map($row): array
    {
        return [
            $row->name,
        ];
    }
}
