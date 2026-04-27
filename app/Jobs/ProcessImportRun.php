<?php

namespace App\Jobs;

use App\Models\ImportRun;
use App\Services\Imports\EnrollmentImportRowProcessor;
use App\Services\Imports\StudentImportRowProcessor;
use App\Services\Imports\TeacherImportRowProcessor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ProcessImportRun implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public array $backoff = [10, 30, 60];

    public function __construct(
        public int $importRunId
    ) {}

    public int $uniqueFor = 3600;

    public function uniqueId(): string
    {
        return 'process_import_run_'.$this->importRunId;
    }

    public function handle(
        StudentImportRowProcessor $studentProcessor,
        TeacherImportRowProcessor $teacherProcessor,
        EnrollmentImportRowProcessor $enrollmentProcessor
    ): void
    {
        $importRun = ImportRun::query()->find($this->importRunId);
        if (! $importRun || $importRun->isFinal()) {
            return;
        }

        $importRun->update([
            'status' => ImportRun::STATUS_PROCESSING,
            'started_at' => now(),
            'error_message' => null,
        ]);

        $errors = [];

        try {
            $rows = $this->loadRows($importRun->file_path);
            $totalRows = count($rows);
            $importRun->update(['total_rows' => $totalRows]);

            foreach (array_chunk($rows, 500) as $chunk) {
                foreach ($chunk as $row) {
                    $result = $this->processRow($importRun, $row, $studentProcessor, $teacherProcessor, $enrollmentProcessor);
                    $this->applyResult($importRun, $result);

                    if (! empty($result['message'])) {
                        $errors[] = [
                            'row' => $row['_row'] ?? '-',
                            'message' => $result['message'],
                        ];
                    }
                }
            }

            $errorReportPath = null;
            if (! empty($errors)) {
                $errorReportPath = $this->writeErrorCsv($importRun, $errors);
            }

            $importRun->update([
                'status' => ImportRun::STATUS_COMPLETED,
                'finished_at' => now(),
                'error_report_path' => $errorReportPath,
                'meta' => ['error_count' => count($errors)],
            ]);
        } catch (Throwable $e) {
            $importRun->update([
                'status' => ImportRun::STATUS_FAILED,
                'finished_at' => now(),
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function loadRows(string $filePath): array
    {
        $reader = new class implements ToArray
        {
            public array $rows = [];

            public function array(array $array): void
            {
                $this->rows = $array;
            }
        };

        Excel::import($reader, $filePath, 'local');

        $sheetRows = $reader->rows;
        if (count($sheetRows) < 2) {
            return [];
        }

        $headers = array_map(fn ($header) => $this->normalizeHeader((string) $header), $sheetRows[0] ?? []);
        $result = [];

        foreach (array_slice($sheetRows, 1) as $index => $row) {
            $assoc = [];
            foreach ($headers as $col => $header) {
                if ($header === '') {
                    continue;
                }

                $value = $row[$col] ?? null;
                $assoc[$header] = $this->normalizeValue($value);
            }

            if ($this->isEmptyRow($assoc)) {
                continue;
            }

            $assoc['_row'] = $index + 2;
            $result[] = $assoc;
        }

        return $result;
    }

    protected function processRow(
        ImportRun $importRun,
        array $row,
        StudentImportRowProcessor $studentProcessor,
        TeacherImportRowProcessor $teacherProcessor,
        EnrollmentImportRowProcessor $enrollmentProcessor
    ): array
    {
        if ($importRun->type === ImportRun::TYPE_STUDENTS) {
            return $studentProcessor->process($row, $importRun->strategy);
        }

        if ($importRun->type === ImportRun::TYPE_ENROLLMENTS) {
            return $enrollmentProcessor->process($row, $importRun->strategy);
        }

        return $teacherProcessor->process($row, $importRun->strategy);
    }

    protected function applyResult(ImportRun $importRun, array $result): void
    {
        $updates = [
            'processed_rows' => $importRun->processed_rows + 1,
        ];

        if ($result['status'] === 'created') {
            $updates['created_count'] = $importRun->created_count + 1;
        } elseif ($result['status'] === 'updated') {
            $updates['updated_count'] = $importRun->updated_count + 1;
        } elseif ($result['status'] === 'skipped') {
            $updates['skipped_count'] = $importRun->skipped_count + 1;
        } else {
            $updates['failed_count'] = $importRun->failed_count + 1;
        }

        $importRun->update($updates);
        $importRun->refresh();
    }

    protected function writeErrorCsv(ImportRun $importRun, array $errors): string
    {
        $directory = 'imports/errors';
        Storage::disk('local')->makeDirectory($directory);
        $path = $directory.'/import-errors-'.$importRun->uuid.'.csv';

        $content = "row,message\n";
        foreach ($errors as $error) {
            $row = str_replace('"', '""', (string) ($error['row'] ?? '-'));
            $message = str_replace('"', '""', (string) ($error['message'] ?? 'Unknown error'));
            $content .= "\"{$row}\",\"{$message}\"\n";
        }

        Storage::disk('local')->put($path, $content);

        return $path;
    }

    protected function normalizeHeader(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = str_replace([' ', '-'], '_', $normalized);

        return preg_replace('/[^a-z0-9_]/', '', $normalized) ?? '';
    }

    protected function normalizeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    protected function isEmptyRow(array $row): bool
    {
        foreach ($row as $key => $value) {
            if ($key === '_row') {
                continue;
            }

            if ($value !== null && $value !== '') {
                return false;
            }
        }

        return true;
    }
}
