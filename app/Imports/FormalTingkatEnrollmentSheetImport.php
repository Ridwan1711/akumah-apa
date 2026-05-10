<?php

namespace App\Imports;

use App\Models\AcademicYear;
use App\Models\EnrollmentTingkatSekolah;
use App\Models\Student;
use App\Models\TingkatSekolah;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class FormalTingkatEnrollmentSheetImport implements ToCollection, WithHeadingRow
{
    public function __construct(
        private readonly FormalTingkatImportResult $result,
        private readonly string $enrollmentStrategy = 'skip',
    ) {}

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $nis = trim((string) ($row['nis'] ?? ''));

            if ($nis === '') {
                continue;
            }

            $yearId = $this->resolveAcademicYearId($row);
            if ($yearId === null) {
                $this->result->failed++;
                $this->result->errors[] = ['row' => $rowNumber, 'message' => 'Enrollment: academic_year_name atau academic_year_id tidak valid.'];

                continue;
            }

            $tingkatId = $this->resolveTingkatSekolahId($row);
            if ($tingkatId === null) {
                $this->result->failed++;
                $this->result->errors[] = ['row' => $rowNumber, 'message' => 'Enrollment: tingkat_code atau tingkat_sekolah_id tidak valid.'];

                continue;
            }

            $student = Student::query()
                ->where('nis', $nis)
                ->where('status', Student::STATUS_ACTIVE)
                ->first();

            if ($student === null) {
                $this->result->failed++;
                $this->result->errors[] = ['row' => $rowNumber, 'message' => "Enrollment: santri aktif dengan NIS {$nis} tidak ditemukan."];

                continue;
            }

            try {
                DB::transaction(function () use ($student, $yearId, $tingkatId, $rowNumber): void {
                    $existing = EnrollmentTingkatSekolah::query()
                        ->where('student_id', $student->id)
                        ->where('academic_year_id', $yearId)
                        ->first();

                    if ($existing !== null) {
                        if ($this->enrollmentStrategy !== 'replace') {
                            $this->result->skipped++;

                            return;
                        }

                        if ((int) $existing->tingkat_sekolah_id === $tingkatId) {
                            $this->result->skipped++;

                            return;
                        }

                        $existing->update(['tingkat_sekolah_id' => $tingkatId]);
                        $this->result->updated++;
                        $this->result->processed++;

                        return;
                    }

                    EnrollmentTingkatSekolah::query()->create([
                        'student_id' => $student->id,
                        'academic_year_id' => $yearId,
                        'tingkat_sekolah_id' => $tingkatId,
                    ]);

                    $this->result->created++;
                    $this->result->processed++;
                });
            } catch (\Throwable $e) {
                $this->result->failed++;
                $this->result->errors[] = ['row' => $rowNumber, 'message' => 'Enrollment: '.$e->getMessage()];
            }
        }
    }

    /**
     * @param  Collection<int, mixed>  $row
     */
    private function resolveAcademicYearId(Collection $row): ?int
    {
        $id = (int) ($row['academic_year_id'] ?? 0);
        if ($id > 0 && AcademicYear::query()->whereKey($id)->exists()) {
            return $id;
        }

        $name = trim((string) ($row['academic_year_name'] ?? ''));
        if ($name === '') {
            return null;
        }

        $found = AcademicYear::query()
            ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
            ->value('id');

        return $found !== null ? (int) $found : null;
    }

    /**
     * @param  Collection<int, mixed>  $row
     */
    private function resolveTingkatSekolahId(Collection $row): ?int
    {
        $id = (int) ($row['tingkat_sekolah_id'] ?? 0);
        if ($id > 0 && TingkatSekolah::query()->whereKey($id)->exists()) {
            return $id;
        }

        $code = trim((string) ($row['tingkat_code'] ?? ''));
        if ($code === '') {
            return null;
        }

        $found = TingkatSekolah::query()
            ->whereRaw('LOWER(code) = ?', [Str::lower($code)])
            ->value('id');

        return $found !== null ? (int) $found : null;
    }
}
