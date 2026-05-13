<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class MasterAccountCredentialsExport implements FromCollection, WithHeadings
{
    /**
     * @param  array<int, array{nis: string, studentName: string, username: string, password: string, waliUsername: string, waliPassword: string}>  $rows
     */
    public function __construct(
        protected array $rows
    ) {}

    public function collection(): Collection
    {
        return collect($this->rows)->map(fn (array $r) => [
            $r['nis'] ?? '',
            $r['studentName'] ?? '',
            $r['username'] ?? '',
            $r['password'] ?? '',
            $r['waliUsername'] ?? '',
            $r['waliPassword'] ?? '',
        ]);
    }

    public function headings(): array
    {
        return [
            'NIS',
            'Santri',
            'Username',
            'Password',
            'Username Wali',
            'Password Wali',
        ];
    }
}
