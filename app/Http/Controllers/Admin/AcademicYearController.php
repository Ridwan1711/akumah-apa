<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAcademicYearRequest;
use App\Http\Requests\Admin\StoreSemesterRequest;
use App\Http\Requests\Admin\UpdateAcademicYearRequest;
use App\Http\Requests\Admin\UpdateSemesterRequest;
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
            'academicYears' => AcademicYear::with('semesters')
                ->orderByDesc('start_date')
                ->get(),
        ]);
    }

    public function store(StoreAcademicYearRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            if ($request->boolean('is_active')) {
                AcademicYear::where('is_active', true)->update(['is_active' => false]);
            }

            AcademicYear::create($request->validated());
        });

        return redirect()->route('admin.academic-years.index')
            ->with('success', 'Tahun ajaran berhasil ditambahkan.');
    }

    public function update(UpdateAcademicYearRequest $request, AcademicYear $academicYear): RedirectResponse
    {
        DB::transaction(function () use ($request, $academicYear) {
            if ($request->boolean('is_active')) {
                AcademicYear::where('is_active', true)
                    ->where('id', '!=', $academicYear->id)
                    ->update(['is_active' => false]);
            }

            $academicYear->update($request->validated());
        });

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
            if ($request->boolean('is_active')) {
                Semester::where('is_active', true)->update(['is_active' => false]);
                $this->resetClassGenderRulesForNewSemester();
            }

            Semester::create($request->validated());
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
            $wasActive = (bool) $semester->is_active;
            if ($request->boolean('is_active')) {
                Semester::where('is_active', true)
                    ->where('id', '!=', $semester->id)
                    ->update(['is_active' => false]);
                if (! $wasActive) {
                    $this->resetClassGenderRulesForNewSemester();
                }
            }

            $semester->update($request->validated());
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
