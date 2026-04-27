<?php

namespace App\Exports;

use App\Models\Student;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StudentDataExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(
        protected array $filters = []
    ) {}

    public function query()
    {
        return Student::query()
            ->with(['currentClass:id,name', 'user:id'])
            ->when($this->filters['search'] ?? null, fn ($q, $search) => $q->where(function ($inner) use ($search) {
                $inner->where('full_name', 'ilike', "%{$search}%")
                    ->orWhere('nis', 'ilike', "%{$search}%");
            }))
            ->when($this->filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($this->filters['class_id'] ?? null, fn ($q, $classId) => $q->where('current_class_id', $classId))
            ->orderBy('full_name');
    }

    public function headings(): array
    {
        return [
            'NIS',
            'NIK',
            'Nama Lengkap',
            'Tempat Lahir',
            'Tanggal Lahir',
            'Gender (L/P)',
            'Alamat',
            'Status',
            'Tahun Masuk',
            'Kelas Saat Ini',
            'Memiliki Akun',
        ];
    }

    public function map($student): array
    {
        return [
            $student->nis,
            $student->nik,
            $student->full_name,
            $student->birth_place,
            $student->birth_date?->format('Y-m-d'),
            $student->gender,
            $student->address,
            $student->status,
            $student->admission_year,
            $student->currentClass?->name,
            $student->user_id ? 'Ya' : 'Tidak',
        ];
    }
}
