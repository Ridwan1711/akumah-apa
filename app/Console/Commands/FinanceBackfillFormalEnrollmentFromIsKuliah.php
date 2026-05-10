<?php

namespace App\Console\Commands;

use App\Models\EnrollmentTingkatSekolah;
use App\Models\Student;
use App\Models\TingkatSekolah;
use Illuminate\Console\Command;

class FinanceBackfillFormalEnrollmentFromIsKuliah extends Command
{
    protected $signature = 'finance:backfill-formal-enrollment-from-is-kuliah {academic_year_id : ID tahun ajaran target}';

    protected $description = 'Untuk santri is_kuliah=true, set enrollment tingkat formal ke tingkat Kuliah pada tahun ajaran tersebut';

    public function handle(): int
    {
        $academicYearId = (int) $this->argument('academic_year_id');
        $kuliahId = TingkatSekolah::query()->where('code', TingkatSekolah::CODE_KULIAH)->value('id');

        if ($kuliahId === null) {
            $this->error('Baris TingkatSekolah dengan code=kuliah belum ada. Jalankan db:seed --class=TingkatSekolahSeeder');

            return self::FAILURE;
        }

        $count = 0;
        Student::query()
            ->where('is_kuliah', true)
            ->where('status', Student::STATUS_ACTIVE)
            ->orderBy('id')
            ->chunkById(200, function ($students) use ($academicYearId, $kuliahId, &$count): void {
                foreach ($students as $student) {
                    EnrollmentTingkatSekolah::query()->updateOrCreate(
                        [
                            'student_id' => $student->id,
                            'academic_year_id' => $academicYearId,
                        ],
                        [
                            'tingkat_sekolah_id' => $kuliahId,
                        ]
                    );
                    $count++;
                }
            });

        $this->info("Diperbarui/dibuat enrollment formal Kuliah untuk {$count} santri.");

        return self::SUCCESS;
    }
}
