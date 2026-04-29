<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAcademicYearRequest;
use App\Http\Requests\Admin\StoreSemesterRequest;
use App\Http\Requests\Admin\UpdateAcademicYearRequest;
use App\Http\Requests\Admin\UpdateSemesterRequest;
use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\Diniyyah\SchoolClass;
use App\Models\Semester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AcademicYearController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/academic-years/index', [
            'academicYears' => AcademicYear::query()
                ->withActivePeriodFlag()
                ->with(['semesters' => fn ($query) => $query->withActivePeriodFlag()])
                ->orderByDesc('start_date')
                ->get(),
        ]);
    }

    public function store(StoreAcademicYearRequest $request): RedirectResponse
    {
        AcademicYear::create($request->validated());

        return redirect()->route('admin.academic-years.index')
            ->with('success', 'Tahun ajaran berhasil ditambahkan.');
    }

    public function update(UpdateAcademicYearRequest $request, AcademicYear $academicYear): RedirectResponse
    {
        $academicYear->update($request->validated());

        return redirect()->route('admin.academic-years.index')
            ->with('success', 'Tahun ajaran berhasil diperbarui.');
    }

    public function destroy(AcademicYear $academicYear): RedirectResponse
    {
        $academicYear->delete();

        return redirect()->route('admin.academic-years.index')
            ->with('success', 'Tahun ajaran berhasil dihapus.');
    }

    public function storeSemester(StoreSemesterRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $semester = Semester::create($request->validated());
            $isActive = $request->boolean('is_active');

            if ($isActive) {
                AcademicPeriod::query()->where('is_active', true)->update(['is_active' => false]);
                $this->resetClassGenderRulesForNewSemester();
            }

            AcademicPeriod::create([
                'academic_year_id' => $request->academic_year_id,
                'semester_id' => $semester->id,
                'is_active' => $isActive,
            ]);
        });

        return redirect()->route('admin.academic-years.index')
            ->with('success', 'Semester berhasil ditambahkan.');
    }

    public function destroySemester(Semester $semester): RedirectResponse
    {
        $semester->delete();

        return redirect()->route('admin.academic-years.index')
            ->with('success', 'Semester berhasil dihapus.');
    }

    public function updateSemester(UpdateSemesterRequest $request, Semester $semester): RedirectResponse
    {
        DB::transaction(function () use ($request, $semester) {
            $semester->update($request->validated());

            $period = AcademicPeriod::query()->firstOrNew(['semester_id' => $semester->id]);
            $wasActive = (bool) $period->is_active;
            $isActive = $request->boolean('is_active');

            if ($isActive) {
                AcademicPeriod::query()
                    ->where('is_active', true)
                    ->where('semester_id', '!=', $semester->id)
                    ->update(['is_active' => false]);

                if (! $wasActive) {
                    $this->resetClassGenderRulesForNewSemester();
                }
            }

            $period->fill([
                'academic_year_id' => $semester->academic_year_id,
                'is_active' => $isActive,
            ]);
            $period->save();
        });

        return redirect()->route('admin.academic-years.index')
            ->with('success', 'Semester berhasil diperbarui.');
    }

    /**
     * Semester baru: aturan gender kelas direset supaya diatur ulang manual.
     */
    private function resetClassGenderRulesForNewSemester(): void
    {
        SchoolClass::query()->update(['student_gender' => null]);
    }
}
