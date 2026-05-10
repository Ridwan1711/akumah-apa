<?php

namespace App\Imports;

use App\Models\AcademicYear;
use App\Models\DormAssignment;
use App\Models\DormBuilding;
use App\Models\DormRoom;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class DormPenempatanSheetImport implements ToCollection, WithHeadingRow
{
    public function __construct(
        private readonly DormImportResult $result,
        private readonly string $placementStrategy = 'skip',
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
                $this->result->errors[] = ['row' => $rowNumber, 'message' => 'Penempatan: academic_year_name atau academic_year_id tidak valid.'];

                continue;
            }

            $buildingName = trim((string) ($row['building_name'] ?? ''));
            $roomNumber = trim((string) ($row['room_number'] ?? ''));
            if ($buildingName === '' || $roomNumber === '') {
                $this->result->failed++;
                $this->result->errors[] = ['row' => $rowNumber, 'message' => 'Penempatan: building_name dan room_number wajib.'];

                continue;
            }

            $checkin = $this->parseDate($row['checkin_date'] ?? null);
            if ($checkin === null) {
                $this->result->failed++;
                $this->result->errors[] = ['row' => $rowNumber, 'message' => 'Penempatan: checkin_date wajib dan harus valid.'];

                continue;
            }

            $checkout = $this->parseDate($row['checkout_date'] ?? null);

            $student = Student::query()
                ->where('nis', $nis)
                ->where('status', Student::STATUS_ACTIVE)
                ->first();

            if ($student === null) {
                $this->result->failed++;
                $this->result->errors[] = ['row' => $rowNumber, 'message' => "Penempatan: santri aktif dengan NIS {$nis} tidak ditemukan."];

                continue;
            }

            $building = DormBuilding::query()->where('name', $buildingName)->first();
            if ($building === null) {
                $this->result->failed++;
                $this->result->errors[] = ['row' => $rowNumber, 'message' => "Penempatan: gedung \"{$buildingName}\" tidak ditemukan."];

                continue;
            }

            $room = DormRoom::query()
                ->where('building_id', $building->id)
                ->where('room_number', $roomNumber)
                ->first();

            if ($room === null) {
                $this->result->failed++;
                $this->result->errors[] = ['row' => $rowNumber, 'message' => "Penempatan: kamar {$roomNumber} di gedung tersebut tidak ditemukan."];

                continue;
            }

            try {
                DB::transaction(function () use ($student, $room, $yearId, $checkin, $checkout, $rowNumber): void {
                    $existingActive = DormAssignment::query()
                        ->where('student_id', $student->id)
                        ->activeInAcademicYear($yearId)
                        ->exists();

                    if ($existingActive && $this->placementStrategy !== 'replace') {
                        $this->result->skipped++;

                        return;
                    }

                    if ($existingActive && $this->placementStrategy === 'replace') {
                        DormAssignment::query()
                            ->where('student_id', $student->id)
                            ->activeInAcademicYear($yearId)
                            ->update(['checkout_date' => now()->toDateString()]);
                    }

                    $occupied = DormAssignment::query()
                        ->where('room_id', $room->id)
                        ->activeInAcademicYear($yearId)
                        ->count();

                    if ($occupied >= (int) $room->capacity) {
                        $this->result->failed++;
                        $this->result->errors[] = ['row' => $rowNumber, 'message' => 'Penempatan: kapasitas kamar penuh untuk tahun ajaran ini.'];

                        return;
                    }

                    DormAssignment::query()->create([
                        'student_id' => $student->id,
                        'room_id' => $room->id,
                        'academic_year_id' => $yearId,
                        'checkin_date' => $checkin->toDateString(),
                        'checkout_date' => $checkout?->toDateString(),
                    ]);

                    $this->result->created++;
                    $this->result->processed++;
                });
            } catch (\Throwable $e) {
                $this->result->failed++;
                $this->result->errors[] = ['row' => $rowNumber, 'message' => 'Penempatan: '.$e->getMessage()];
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

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->startOfDay();
        }

        if (is_numeric($value)) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->startOfDay();
            } catch (\Throwable) {
                // fall through
            }
        }

        try {
            return Carbon::parse((string) $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
