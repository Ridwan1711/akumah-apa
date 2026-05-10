<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\EnrollmentTingkatSekolah;
use App\Models\Student;
use App\Models\TingkatSekolah;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FormalTingkatEnrollmentController extends Controller
{
    public function assign(Request $request): Response
    {
        $selectedAcademicYearId = $this->resolveAcademicYearId((int) $request->input('academic_year_id', 0));
        $perPageOptions = [25, 50, 75, 100];
        $perPage = (int) $request->input('per_page', 25);
        if (! in_array($perPage, $perPageOptions, true)) {
            $perPage = 25;
        }

        $unassigned = Student::where('status', Student::STATUS_ACTIVE)
            ->whereDoesntHave('enrollmentTingkatSekolahs', fn ($q) => $q->where('academic_year_id', $selectedAcademicYearId))
            ->orderBy('full_name')
            ->paginate($perPage, ['id', 'nis', 'full_name'])
            ->withQueryString();

        $sourceAcademicYear = AcademicYear::query()
            ->where('id', '<', $selectedAcademicYearId)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first(['id', 'name']);

        return Inertia::render('admin/formal-tingkat/assign', [
            'unassignedStudents' => $unassigned,
            'filters' => $request->only(['per_page', 'academic_year_id']),
            'perPageOptions' => $perPageOptions,
            'academicYears' => $this->academicYears(),
            'selectedAcademicYearId' => $selectedAcademicYearId,
            'tingkatSekolahs' => TingkatSekolah::query()
                ->orderBy('order')
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'group']),
            'copySourceAcademicYear' => $sourceAcademicYear,
        ]);
    }

    public function storeEnrollment(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['required', 'integer', 'distinct', 'exists:students,id'],
            'tingkat_sekolah_id' => ['required', 'exists:tingkat_sekolahs,id'],
            'academic_year_id' => ['required', 'exists:academic_years,id'],
        ]);

        $academicYearId = (int) $validated['academic_year_id'];
        $tingkatId = (int) $validated['tingkat_sekolah_id'];
        $studentIds = collect($validated['student_ids'])->map(fn ($id) => (int) $id)->unique()->values();

        $count = DB::transaction(function () use ($studentIds, $academicYearId, $tingkatId): int {
            $n = 0;
            foreach ($studentIds as $studentId) {
                EnrollmentTingkatSekolah::query()->updateOrCreate(
                    [
                        'student_id' => $studentId,
                        'academic_year_id' => $academicYearId,
                    ],
                    [
                        'tingkat_sekolah_id' => $tingkatId,
                    ]
                );
                $n++;
            }

            return $n;
        });

        return redirect()->back()->with('success', "{$count} santri berhasil di-assign tingkat formal untuk tahun ajaran terpilih.");
    }

    public function copyEnrollmentsFromAcademicYear(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'source_academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'target_academic_year_id' => ['required', 'integer', 'exists:academic_years,id', 'different:source_academic_year_id'],
        ]);

        $sourceId = (int) $validated['source_academic_year_id'];
        $targetId = (int) $validated['target_academic_year_id'];

        $sources = EnrollmentTingkatSekolah::query()
            ->where('academic_year_id', $sourceId)
            ->get(['student_id', 'tingkat_sekolah_id']);

        if ($sources->isEmpty()) {
            return redirect()->back()->with('error', 'Tidak ada enrollment tingkat formal di tahun ajaran sumber.');
        }

        $existingTarget = EnrollmentTingkatSekolah::query()
            ->where('academic_year_id', $targetId)
            ->pluck('student_id')
            ->map(fn ($id) => (int) $id)
            ->flip();

        $created = 0;
        $skipped = 0;
        $batch = [];

        foreach ($sources as $src) {
            $sid = (int) $src->student_id;
            if (isset($existingTarget[$sid])) {
                $skipped++;

                continue;
            }
            $existingTarget[$sid] = true;
            $batch[] = [
                'student_id' => $sid,
                'academic_year_id' => $targetId,
                'tingkat_sekolah_id' => (int) $src->tingkat_sekolah_id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $created++;
        }

        if ($batch !== []) {
            EnrollmentTingkatSekolah::query()->insert($batch);
        }

        return redirect()->back()->with(
            'success',
            "Copy enrollment tingkat formal selesai. Ditambahkan {$created}, dilewati (sudah ada di TA target) {$skipped}."
        );
    }

    private function resolveAcademicYearId(?int $requestedAcademicYearId = null): int
    {
        if (($requestedAcademicYearId ?? 0) > 0 && AcademicYear::query()->whereKey($requestedAcademicYearId)->exists()) {
            return (int) $requestedAcademicYearId;
        }

        $activeAcademicYearId = AcademicPeriod::query()
            ->active()
            ->value('academic_year_id');
        if ($activeAcademicYearId) {
            return (int) $activeAcademicYearId;
        }

        return (int) (AcademicYear::query()->orderByDesc('start_date')->value('id')
            ?? AcademicYear::query()->orderByDesc('id')->value('id'));
    }

    /**
     * @return \Illuminate\Support\Collection<int, AcademicYear>
     */
    private function academicYears()
    {
        return AcademicYear::query()
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get(['id', 'name', 'start_date', 'end_date']);
    }
}
