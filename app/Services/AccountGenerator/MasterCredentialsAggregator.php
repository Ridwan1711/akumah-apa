<?php

namespace App\Services\AccountGenerator;

use App\Models\ImportRun;
use App\Support\AccountGeneratorCredentialsFormatter;
use Illuminate\Support\Facades\Storage;

/**
 * Menggabungkan kredensial plaintext dari semua job generate akun yang tersimpan
 * (payload JSON + file TSV di storage). Job lebih baru menimpa baris dengan kunci sama (NIS / username wali).
 */
final class MasterCredentialsAggregator
{
    /**
     * @return array<int, array{nis: string, studentName: string, username: string, password: string, waliUsername: string, waliPassword: string}>
     */
    public static function aggregate(): array
    {
        $runs = ImportRun::query()
            ->whereIn('job_type', [
                ImportRun::JOB_ACCOUNT_GENERATE_STUDENTS,
                ImportRun::JOB_ACCOUNT_GENERATE_GUARDIANS,
            ])
            ->where('status', ImportRun::STATUS_COMPLETED)
            ->orderByDesc('id')
            ->get(['id', 'result_payload']);

        $byKey = [];

        foreach ($runs as $run) {
            foreach (self::rowsFromRun($run) as $row) {
                $byKey[self::dedupeKey($row)] = $row;
            }
        }

        $rows = array_values($byKey);
        usort($rows, function (array $a, array $b): int {
            $na = $a['nis'] ?? '';
            $nb = $b['nis'] ?? '';
            if ($na !== $nb) {
                return strcmp($na, $nb);
            }

            return strcmp($a['waliUsername'] ?? '', $b['waliUsername'] ?? '');
        });

        return $rows;
    }

    /**
     * @return array<int, array{nis: string, studentName: string, username: string, password: string, waliUsername: string, waliPassword: string}>
     */
    public static function rowsFromRun(ImportRun $run): array
    {
        $payload = $run->result_payload;
        if (! is_array($payload)) {
            return [];
        }

        $path = $payload['credentials_full_export_path'] ?? null;
        if (is_string($path) && $path !== '' && Storage::disk('local')->exists($path)) {
            $raw = Storage::disk('local')->get($path);
            $parsed = self::parseTsvCredentialsFile($raw);
            if ($parsed !== []) {
                return $parsed;
            }
        }

        return AccountGeneratorCredentialsFormatter::mergeFromPayload($payload);
    }

    /**
     * @return array<int, array{nis: string, studentName: string, username: string, password: string, waliUsername: string, waliPassword: string}>
     */
    public static function parseTsvCredentialsFile(string $raw): array
    {
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        $out = [];
        $lineIndex = 0;

        foreach ($lines as $line) {
            $line = rtrim($line, "\r\n");
            if ($line === '') {
                continue;
            }
            $parts = explode("\t", $line);
            if ($lineIndex === 0 && ($parts[0] ?? '') === 'NIS') {
                $lineIndex++;

                continue;
            }
            $lineIndex++;

            while (count($parts) < 6) {
                $parts[] = '';
            }
            $out[] = [
                'nis' => $parts[0] ?? '',
                'studentName' => $parts[1] ?? '',
                'username' => $parts[2] ?? '',
                'password' => $parts[3] ?? '',
                'waliUsername' => $parts[4] ?? '',
                'waliPassword' => $parts[5] ?? '',
            ];
        }

        return $out;
    }

    /**
     * @param  array{nis?: string, studentName?: string, username?: string, password?: string, waliUsername?: string, waliPassword?: string}  $row
     */
    public static function dedupeKey(array $row): string
    {
        $nis = trim((string) ($row['nis'] ?? ''));
        if ($nis !== '') {
            return 'nis:'.$nis;
        }

        return 'wali:'.trim((string) ($row['waliUsername'] ?? ''));
    }
}
