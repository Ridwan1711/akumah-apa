<?php

namespace App\Services\AccountGenerator;

use App\Models\Guardian;
use App\Models\ImportRun;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class AccountGenerateRollbackService
{
    /**
     * Hapus akun user yang dibuat oleh job generate akun ini (santri + wali), hapus wali placeholder
     * otomatis (baris `Wali {nama}` tanpa akun, hanya terikat satu santri dari job), lalu tandai job dibatalkan.
     * Hanya user yang memenuhi pola generate otomatis (email test + satu role) yang dihapus.
     *
     * @return array{santri_removed: int, wali_removed: int, placeholders_removed: int, skipped_usernames: array<int, string>, warnings: array<int, string>}
     */
    public function rollback(ImportRun $importRun): array
    {
        if (! in_array($importRun->job_type, [
            ImportRun::JOB_ACCOUNT_GENERATE_STUDENTS,
            ImportRun::JOB_ACCOUNT_GENERATE_GUARDIANS,
        ], true)) {
            throw new \InvalidArgumentException('Job ini bukan generate akun santri/wali.');
        }

        if ($importRun->status !== ImportRun::STATUS_COMPLETED) {
            throw new \RuntimeException('Hanya job berstatus selesai yang bisa di-rollback.');
        }

        $payloadStart = $importRun->result_payload;
        if (is_array($payloadStart) && isset($payloadStart['rolled_back_at'])) {
            throw new \RuntimeException('Job ini sudah pernah di-rollback.');
        }

        $exportPath = is_array($payloadStart) ? ($payloadStart['credentials_full_export_path'] ?? null) : null;

        $rows = MasterCredentialsAggregator::rowsFromRun($importRun);
        if ($rows === []) {
            throw new \RuntimeException('Tidak ada baris kredensial tersimpan untuk job ini (payload kosong / terpotong). Rollback otomatis tidak aman.');
        }

        $waliUsernames = [];
        $santriUsernames = [];
        foreach ($rows as $row) {
            $wu = trim((string) ($row['waliUsername'] ?? ''));
            $su = trim((string) ($row['username'] ?? ''));
            if ($wu !== '') {
                $waliUsernames[] = $wu;
            }
            if ($su !== '') {
                $santriUsernames[] = $su;
            }
        }
        $waliUsernames = array_values(array_unique($waliUsernames));
        $santriUsernames = array_values(array_unique($santriUsernames));

        return DB::transaction(function () use (
            $importRun,
            $waliUsernames,
            $santriUsernames,
            $exportPath
        ): array {
            $skipped = [];
            $warnings = [];
            $waliRemoved = 0;
            $santriRemoved = 0;

            foreach ($waliUsernames as $username) {
                $user = User::query()->where('username', $username)->first();
                if (! $this->isRollbackableWaliUser($user)) {
                    $skipped[] = 'wali:'.$username;

                    continue;
                }
                $this->unlinkWaliAndDeleteUser($user);
                $waliRemoved++;
            }

            foreach ($santriUsernames as $username) {
                $user = User::query()->where('username', $username)->first();
                if (! $this->isRollbackableSantriUser($user)) {
                    $skipped[] = 'santri:'.$username;

                    continue;
                }
                $this->unlinkSantriAndDeleteUser($user);
                $santriRemoved++;
            }

            if ($waliRemoved + $santriRemoved === 0) {
                throw new \RuntimeException('Tidak ada akun yang dihapus (semua username di-skip). Periksa apakah user sudah diubah manual atau bukan akun generate otomatis.');
            }

            $placeholdersRemoved = $this->removeAutoPlaceholderGuardians($importRun);

            if (is_string($exportPath) && $exportPath !== '' && Storage::disk('local')->exists($exportPath)) {
                Storage::disk('local')->delete($exportPath);
            }

            $importRun->update([
                'status' => ImportRun::STATUS_CANCELLED,
                'result_payload' => [
                    'rolled_back_at' => now()->toIso8601String(),
                    'rolled_back_summary' => [
                        'santri_removed' => $santriRemoved,
                        'wali_removed' => $waliRemoved,
                        'placeholders_removed' => $placeholdersRemoved,
                        'skipped' => $skipped,
                    ],
                ],
            ]);

            if ($skipped !== []) {
                $warnings[] = 'Beberapa username dilewati karena tidak cocok dengan pola akun generate otomatis: '.implode(', ', array_slice($skipped, 0, 20))
                    .(count($skipped) > 20 ? '…' : '');
            }

            return [
                'santri_removed' => $santriRemoved,
                'wali_removed' => $waliRemoved,
                'placeholders_removed' => $placeholdersRemoved,
                'skipped_usernames' => $skipped,
                'warnings' => $warnings,
            ];
        });
    }

    /**
     * Hapus baris wali yang dibuat otomatis oleh {@see ProcessBulkRun::ensureWaliAccountsForStudent}:
     * nama persis `Wali {nama santri}`, tanpa akun user, dan hanya terikat satu santri (santri ada di meta job).
     */
    private function removeAutoPlaceholderGuardians(ImportRun $importRun): int
    {
        if ($importRun->job_type !== ImportRun::JOB_ACCOUNT_GENERATE_STUDENTS) {
            return 0;
        }

        $meta = $importRun->meta;
        if (! is_array($meta)) {
            return 0;
        }

        $studentIds = array_values($meta['student_ids'] ?? []);
        $removed = 0;

        foreach ($studentIds as $studentId) {
            $student = Student::query()->find((int) $studentId);
            if (! $student) {
                continue;
            }

            $expectedFullName = 'Wali '.$student->full_name;

            $guardians = $student->guardians()
                ->where('guardians.full_name', $expectedFullName)
                ->whereNull('guardians.user_id')
                ->get();

            foreach ($guardians as $guardian) {
                if ($guardian->students()->count() !== 1) {
                    continue;
                }
                if ((int) $guardian->students()->first()?->id !== (int) $student->id) {
                    continue;
                }

                $student->guardians()->detach($guardian->id);
                $guardian->delete();
                $removed++;
            }
        }

        return $removed;
    }

    private function isRollbackableWaliUser(?User $user): bool
    {
        if (! $user) {
            return false;
        }
        if (! str_ends_with(strtolower((string) $user->email), '@wali.siakad.test')) {
            return false;
        }
        if (! str_starts_with((string) $user->username, 'wali_')) {
            return false;
        }
        $roles = $user->roles()->pluck('name')->all();

        return count($roles) === 1 && $roles[0] === Role::WALI_SANTRI;
    }

    private function isRollbackableSantriUser(?User $user): bool
    {
        if (! $user) {
            return false;
        }
        if (! str_ends_with(strtolower((string) $user->email), '@santri.siakad.test')) {
            return false;
        }
        $roles = $user->roles()->pluck('name')->all();
        if (count($roles) !== 1 || $roles[0] !== Role::SANTRI) {
            return false;
        }
        $student = Student::query()->where('user_id', $user->id)->first();

        return $student !== null && $student->nis === $user->username;
    }

    private function unlinkWaliAndDeleteUser(User $user): void
    {
        Guardian::query()->where('user_id', $user->id)->update(['user_id' => null]);
        $user->guardians()->detach();
        $user->roles()->detach();
        $user->tokens()->delete();
        $user->delete();
    }

    private function unlinkSantriAndDeleteUser(User $user): void
    {
        Student::query()->where('user_id', $user->id)->update(['user_id' => null]);
        $user->roles()->detach();
        $user->tokens()->delete();
        $user->delete();
    }
}
