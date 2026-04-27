<?php

namespace App\Services\Imports;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TeacherImportRowProcessor
{
    protected ?Role $guruRole;

    public function __construct()
    {
        $this->guruRole = Role::query()->where('name', Role::GURU)->first();
    }

    public function process(array $data, string $strategy): array
    {
        if (! $this->guruRole) {
            return ['status' => 'failed', 'message' => 'Role guru tidak ditemukan.'];
        }

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'is_active' => ['nullable', Rule::in(['0', '1', 'true', 'false'])],
        ]);

        if ($validator->fails()) {
            return ['status' => 'failed', 'message' => $validator->errors()->first()];
        }

        $existing = User::query()->where('email', $data['email'])->first();

        if ($existing && $strategy === 'skip') {
            return ['status' => 'skipped', 'message' => null];
        }

        if (! empty($data['username'])) {
            $usernameOwner = User::query()
                ->where('username', $data['username'])
                ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))
                ->exists();

            if ($usernameOwner) {
                return ['status' => 'failed', 'message' => "Username '{$data['username']}' sudah dipakai akun lain."];
            }
        }

        $payload = [
            'name' => $data['name'],
            'username' => $data['username'] ?: null,
            'email' => $data['email'],
            'is_active' => $this->toBool($data['is_active'] ?? null),
        ];

        if ($existing) {
            $existing->update($payload);
            $existing->roles()->syncWithoutDetaching([$this->guruRole->id]);

            return ['status' => 'updated', 'message' => null];
        }

        $user = User::create([
            ...$payload,
            'password' => Hash::make('password'),
            'must_change_password' => true,
        ]);
        $user->roles()->sync([$this->guruRole->id]);

        return ['status' => 'created', 'message' => null];
    }

    protected function toBool(?string $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return in_array(strtolower($value), ['1', 'true'], true);
    }
}
