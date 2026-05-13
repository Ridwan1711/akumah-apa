<?php

namespace App\Support;

/**
 * Builds merged credential rows + TSV export for account generator jobs.
 * Logic mirrors {@see resources/js/pages/admin/account-generator/index.tsx} normalizeGeneratedCredentials.
 */
final class AccountGeneratorCredentialsFormatter
{
    public const TSV_HEADERS = ['NIS', 'Santri', 'Username', 'Password', 'Username Wali', 'Password Wali'];

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array{nis: string, studentName: string, username: string, password: string, waliUsername: string, waliPassword: string}>
     */
    public static function mergeFromPayload(array $payload): array
    {
        $accounts = isset($payload['generated_accounts']) && is_array($payload['generated_accounts'])
            ? $payload['generated_accounts']
            : [];
        $waliAccounts = isset($payload['generated_wali_accounts']) && is_array($payload['generated_wali_accounts'])
            ? $payload['generated_wali_accounts']
            : [];

        $waliRows = [];
        $studentRows = [];

        foreach ($accounts as $item) {
            if (! is_array($item)) {
                continue;
            }
            $username = self::str($item['username'] ?? null);
            $password = self::str($item['password'] ?? null);
            if ($username === '' || $password === '') {
                continue;
            }

            $guardianName = self::str($item['guardian_name'] ?? null);
            $studentNisField = self::str($item['student_nis'] ?? null);

            if ($guardianName !== '' || $studentNisField !== '') {
                $nis = $studentNisField === '-' ? '' : $studentNisField;
                $waliRows[] = [
                    'nis' => $nis,
                    'studentName' => self::str($item['student_name'] ?? null),
                    'username' => $username,
                    'password' => $password,
                ];

                continue;
            }

            $studentRows[] = [
                'nis' => self::str($item['nis'] ?? null),
                'studentName' => self::str($item['name'] ?? null),
                'username' => $username,
                'password' => $password,
                'waliUsername' => '',
                'waliPassword' => '',
            ];
        }

        foreach ($waliAccounts as $item) {
            if (! is_array($item)) {
                continue;
            }
            $username = self::str($item['username'] ?? null);
            $password = self::str($item['password'] ?? null);
            if ($username === '' || $password === '') {
                continue;
            }
            $nisRaw = self::str($item['student_nis'] ?? null);
            $waliRows[] = [
                'nis' => $nisRaw === '-' ? '' : $nisRaw,
                'studentName' => self::str($item['student_name'] ?? null),
                'username' => $username,
                'password' => $password,
            ];
        }

        if ($studentRows === []) {
            $out = [];
            foreach ($waliRows as $wali) {
                $out[] = [
                    'nis' => $wali['nis'],
                    'studentName' => $wali['studentName'],
                    'username' => '',
                    'password' => '',
                    'waliUsername' => $wali['username'],
                    'waliPassword' => $wali['password'],
                ];
            }

            return $out;
        }

        $usedWaliIndexes = [];
        $mergedRows = [];

        foreach ($studentRows as $studentRow) {
            $matches = [];
            foreach ($waliRows as $index => $wali) {
                if ($wali['nis'] !== '' && $studentRow['nis'] !== '' && $wali['nis'] === $studentRow['nis']) {
                    $matches[] = ['wali' => $wali, 'index' => $index];
                }
            }

            if ($matches === []) {
                $mergedRows[] = $studentRow;

                continue;
            }

            foreach ($matches as $match) {
                $usedWaliIndexes[$match['index']] = true;
                $wali = $match['wali'];
                $mergedRows[] = [
                    'nis' => $studentRow['nis'],
                    'studentName' => $studentRow['studentName'] !== '' ? $studentRow['studentName'] : $wali['studentName'],
                    'username' => $studentRow['username'],
                    'password' => $studentRow['password'],
                    'waliUsername' => $wali['username'],
                    'waliPassword' => $wali['password'],
                ];
            }
        }

        foreach ($waliRows as $index => $wali) {
            if (isset($usedWaliIndexes[$index])) {
                continue;
            }
            $mergedRows[] = [
                'nis' => $wali['nis'],
                'studentName' => $wali['studentName'],
                'username' => '',
                'password' => '',
                'waliUsername' => $wali['username'],
                'waliPassword' => $wali['password'],
            ];
        }

        return $mergedRows;
    }

    /**
     * @param  array<int, array{nis: string, studentName: string, username: string, password: string, waliUsername: string, waliPassword: string}>  $rows
     */
    public static function toTsv(array $rows): string
    {
        $lines = [implode("\t", self::TSV_HEADERS)];

        foreach ($rows as $row) {
            $lines[] = implode("\t", array_map([self::class, 'cleanCell'], [
                $row['nis'] ?? '',
                $row['studentName'] ?? '',
                $row['username'] ?? '',
                $row['password'] ?? '',
                $row['waliUsername'] ?? '',
                $row['waliPassword'] ?? '',
            ]));
        }

        return implode("\n", $lines);
    }

    private static function str(mixed $v): string
    {
        return is_string($v) ? $v : '';
    }

    private static function cleanCell(string $value): string
    {
        $value = str_replace(["\t", "\r", "\n"], [' ', ' ', ' '], $value);

        return trim($value);
    }
}
