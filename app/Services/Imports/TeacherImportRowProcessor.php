<?php

namespace App\Services\Imports;

use App\Models\Role;
use App\Models\Student;
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
            // Mode assign existing (recommended)
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'student_nis' => ['nullable', 'string', 'max:50'],

            // Mode create/update by email (legacy)
            'name' => ['nullable', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],

            // Shared
            'is_active' => ['nullable', Rule::in(['0', '1', 'true', 'false'])],
        ]);

        if ($validator->fails()) {
            return ['status' => 'failed', 'message' => $validator->errors()->first()];
        }

        $existing = $this->resolveExistingUser($data);
        $isAssignExisting = $existing !== null && ($this->hasFilled($data, 'user_id') || $this->hasFilled($data, 'student_nis'));

        // Assign existing user as teacher
        if ($isAssignExisting) {
            $payload = [];
            if (array_key_exists('is_active', $data)) {
                $payload['is_active'] = $this->toBool($data['is_active'] ?? null);
            }

            if ($strategy === 'update') {
                if ($this->hasFilled($data, 'name')) {
                    $payload['name'] = $data['name'];
                }
                if ($this->hasFilled($data, 'username')) {
                    $payload['username'] = $data['username'];
                }
                if ($this->hasFilled($data, 'email')) {
                    $payload['email'] = $data['email'];
                }
            }

            if (! empty($payload)) {
                // Validate username uniqueness on updates.
                if (! empty($payload['username'])) {
                    $usernameOwner = User::query()
                        ->where('username', $payload['username'])
                        ->where('id', '!=', $existing->id)
                        ->exists();

                    if ($usernameOwner) {
                        return ['status' => 'failed', 'message' => "Username '{$payload['username']}' sudah dipakai akun lain."];
                    }
                }

                // Validate email uniqueness on updates.
                if (! empty($payload['email'])) {
                    $emailOwner = User::query()
                        ->where('email', $payload['email'])
                        ->where('id', '!=', $existing->id)
                        ->exists();

                    if ($emailOwner) {
                        return ['status' => 'failed', 'message' => "Email '{$payload['email']}' sudah dipakai akun lain."];
                    }
                }

                $existing->update($payload);
            }

            $existing->roles()->syncWithoutDetaching([$this->guruRole->id]);

            return ['status' => 'updated', 'message' => null];
        }

        // Legacy behavior: create/update teacher by email
        if (! $this->hasFilled($data, 'email')) {
            return ['status' => 'failed', 'message' => 'Kolom email wajib diisi (atau isi user_id / student_nis untuk assign dari user existing).'];
        }
        if (! $this->hasFilled($data, 'name')) {
            return ['status' => 'failed', 'message' => 'Kolom name wajib diisi (atau isi user_id / student_nis untuk assign dari user existing).'];
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

    protected function resolveExistingUser(array $data): ?User
    {
        if ($this->hasFilled($data, 'user_id')) {
            return User::query()->find((int) $data['user_id']);
        }

        if ($this->hasFilled($data, 'student_nis')) {
            $nis = trim((string) $data['student_nis']);
            $student = Student::query()->where('nis', $nis)->first();
            if (! $student) {
                return null;
            }
            if (! $student->user_id) {
                return null;
            }
            return User::query()->find((int) $student->user_id);
        }

        return null;
    }

    protected function toBool(?string $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return in_array(strtolower($value), ['1', 'true'], true);
    }

    protected function hasFilled(array $data, string $key): bool
    {
        if (! array_key_exists($key, $data)) {
            return false;
        }
        $v = $data[$key];
        if ($v === null) {
            return false;
        }
        if (is_string($v)) {
            return trim($v) !== '';
        }
        return $v !== '';
    }
}
