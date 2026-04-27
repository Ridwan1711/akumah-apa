<?php

namespace App\Imports;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class TeacherDataImport implements ToCollection, WithHeadingRow
{
    protected int $processed = 0;

    protected int $created = 0;

    protected int $updated = 0;

    protected int $skipped = 0;

    protected array $errors = [];

    protected ?Role $guruRole = null;

    public function __construct(
        protected string $strategy = 'skip'
    ) {
        $this->guruRole = Role::query()->where('name', Role::GURU)->first();
    }

    public function collection(Collection $rows): void
    {
        if (! $this->guruRole) {
            $this->errors[] = [
                'row' => 0,
                'message' => 'Role guru tidak ditemukan. Import dibatalkan.',
            ];

            return;
        }

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $data = $this->normalizeRow($row->toArray());

            if ($this->isEmptyRow($data)) {
                continue;
            }

            $this->processed++;

            $validator = Validator::make($data, [
                'name' => ['required', 'string', 'max:255'],
                'username' => ['nullable', 'string', 'max:100'],
                'email' => ['required', 'email', 'max:255'],
                'is_active' => ['nullable', Rule::in(['0', '1', 'true', 'false'])],
            ]);

            if ($validator->fails()) {
                $this->errors[] = [
                    'row' => $rowNumber,
                    'message' => $validator->errors()->first(),
                ];
                continue;
            }

            $existing = User::query()->where('email', $data['email'])->first();

            if ($existing && $this->strategy === 'skip') {
                $this->skipped++;
                continue;
            }

            if (! empty($data['username'])) {
                $usernameOwner = User::query()
                    ->where('username', $data['username'])
                    ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))
                    ->exists();

                if ($usernameOwner) {
                    $this->errors[] = [
                        'row' => $rowNumber,
                        'message' => "Username '{$data['username']}' sudah dipakai akun lain.",
                    ];
                    continue;
                }
            }

            $payload = [
                'name' => $data['name'],
                'username' => $data['username'],
                'email' => $data['email'],
                'is_active' => $this->toBool($data['is_active'] ?? null),
            ];

            if ($existing) {
                $existing->update($payload);
                $existing->roles()->syncWithoutDetaching([$this->guruRole->id]);
                $this->updated++;
            } else {
                $user = User::create([
                    ...$payload,
                    'password' => Hash::make('password'),
                    'must_change_password' => true,
                ]);
                $user->roles()->sync([$this->guruRole->id]);
                $this->created++;
            }
        }
    }

    public function result(): array
    {
        return [
            'processed' => $this->processed,
            'created' => $this->created,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'failed' => count($this->errors),
            'errors' => $this->errors,
        ];
    }

    protected function normalizeRow(array $row): array
    {
        return [
            'name' => $this->string($row['name'] ?? null),
            'username' => $this->string($row['username'] ?? null),
            'email' => $this->string($row['email'] ?? null),
            'is_active' => strtolower((string) $this->string($row['is_active'] ?? null)),
        ];
    }

    protected function string(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    protected function toBool(?string $value): bool
    {
        if ($value === null) {
            return true;
        }

        return in_array($value, ['1', 'true'], true);
    }

    protected function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && $value !== '') {
                return false;
            }
        }

        return true;
    }
}
