<?php

namespace App\Exports;

use App\Models\Role;
use App\Models\User;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TeacherDataExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(
        protected array $filters = []
    ) {}

    public function query()
    {
        return User::query()
            ->with('roles:id,name')
            ->whereHas('roles', fn ($q) => $q->where('name', Role::GURU))
            ->when($this->filters['search'] ?? null, fn ($q, $search) => $q->where(function ($inner) use ($search) {
                $inner->where('name', 'ilike', "%{$search}%")
                    ->orWhere('username', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            }))
            ->when(isset($this->filters['status']) && $this->filters['status'] !== '', fn ($q) => $q->where('is_active', $this->filters['status'] === '1'))
            ->orderBy('name');
    }

    public function headings(): array
    {
        return [
            'Nama',
            'Username',
            'Email',
            'Status Aktif (1/0)',
            'Role',
        ];
    }

    public function map($user): array
    {
        return [
            $user->name,
            $user->username,
            $user->email,
            $user->is_active ? 1 : 0,
            $user->roles->pluck('name')->join(', '),
        ];
    }
}
