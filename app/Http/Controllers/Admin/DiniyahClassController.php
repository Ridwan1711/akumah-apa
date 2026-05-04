<?php

namespace App\Http\Controllers\Admin;

use App\Exports\DiniyahClassDataExport;
use App\Exports\DiniyahClassTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportDiniyahClassesRequest;
use App\Http\Requests\Admin\StoreDiniyahClassRequest;
use App\Http\Requests\Admin\UpdateDiniyahClassRequest;
use App\Imports\DiniyahClassDataImport;
use App\Models\AcademicPeriod;
use App\Models\Diniyyah\ClassWali;
use App\Models\Diniyyah\GradeLevel;
use App\Models\Diniyyah\SchoolClass;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class DiniyahClassController extends Controller
{
    public function index(Request $request): Response
    {
        $perPageOptions = [25, 50, 75, 100, 1000];
        $perPage = (int) $request->input('per_page', 25);
        $search = trim((string) $request->input('search', ''));
        if (! in_array($perPage, $perPageOptions, true)) {
            $perPage = 25;
        }

        $activePeriodId = AcademicPeriod::query()->active()->value('id');
        $activePeriod = $activePeriodId
            ? AcademicPeriod::query()
                ->whereKey($activePeriodId)
                ->with(['semester:id,name', 'academicYear:id,name'])
                ->first()
            : null;

        $eager = ['gradeLevel:id,name,order'];
        if ($activePeriodId) {
            $eager['walis'] = fn ($q) => $q->where('period_id', $activePeriodId)
                ->with('teacher:id,name');
        }

        $classQuery = SchoolClass::with($eager)
            ->withCount('students')
            ->when($search !== '', function ($query) use ($search) {
                $query->where('name', 'ilike', "%{$search}%");
            })
            ->orderBy('order')
            ->orderBy('name');

        return Inertia::render('admin/diniyah-classes/index', [
            'classes' => $classQuery
                ->paginate($perPage)
                ->withQueryString(),
            'gradeLevels' => GradeLevel::orderBy('order')->get(['id', 'name', 'order']),
            'filters' => $request->only(['per_page', 'search']),
            'perPageOptions' => $perPageOptions,
            'homeroomTeachers' => User::query()
                ->where('is_active', true)
                ->whereHas('roles', fn ($q) => $q->where('name', Role::GURU))
                ->orderBy('name')
                ->get(['id', 'name']),
            'activeAcademicPeriod' => $activePeriod ? [
                'id' => $activePeriod->id,
                'label' => ($activePeriod->academicYear?->name ?? '—').' · '.($activePeriod->semester?->name ?? '—'),
            ] : null,
        ]);
    }

    public function export(Request $request)
    {
        $format = $request->string('format')->toString() === 'csv' ? 'csv' : 'xlsx';
        $writerType = $format === 'csv'
            ? \Maatwebsite\Excel\Excel::CSV
            : \Maatwebsite\Excel\Excel::XLSX;
        $filename = 'kelas-diniyah-export-'.now()->format('Y-m-d-His').'.'.$format;

        return Excel::download(
            new DiniyahClassDataExport($request->only(['search'])),
            $filename,
            $writerType
        );
    }

    public function template(Request $request)
    {
        $format = $request->string('format')->toString() === 'csv' ? 'csv' : 'xlsx';
        $writerType = $format === 'csv'
            ? \Maatwebsite\Excel\Excel::CSV
            : \Maatwebsite\Excel\Excel::XLSX;
        $filename = 'template-import-kelas-diniyah-v1.'.$format;

        return Excel::download(new DiniyahClassTemplateExport, $filename, $writerType);
    }

    public function import(ImportDiniyahClassesRequest $request): RedirectResponse
    {
        $import = new DiniyahClassDataImport($request->string('strategy')->toString());

        Excel::import($import, $request->file('file'));

        $result = $import->result();
        $summary = sprintf(
            'Import kelas selesai. Diproses: %d, dibuat: %d, diperbarui: %d, dilewati: %d, gagal: %d.',
            $result['processed'],
            $result['created'],
            $result['updated'],
            $result['skipped'],
            $result['failed']
        );

        if ($result['failed'] > 0) {
            $firstError = $result['errors'][0]['message'] ?? 'Periksa format data import Anda.';

            return redirect()->route('admin.diniyah-classes.index')
                ->with('error', $summary.' Error pertama: '.$firstError);
        }

        return redirect()->route('admin.diniyah-classes.index')
            ->with('success', $summary);
    }

    public function store(StoreDiniyahClassRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $class = SchoolClass::create(Arr::except($validated, ['wali_teacher_id']));
        if (array_key_exists('wali_teacher_id', $validated)) {
            $this->syncHomeroomTeacherForActivePeriod($class, $validated['wali_teacher_id']);
        }

        return redirect()->route('admin.diniyah-classes.index')
            ->with('success', 'Kelas diniyah berhasil ditambahkan.');
    }

    public function update(UpdateDiniyahClassRequest $request, SchoolClass $diniyahClass): RedirectResponse
    {
        $validated = $request->validated();
        $diniyahClass->update(Arr::except($validated, ['wali_teacher_id']));
        if (array_key_exists('wali_teacher_id', $validated)) {
            $this->syncHomeroomTeacherForActivePeriod($diniyahClass, $validated['wali_teacher_id']);
        }

        return redirect()->route('admin.diniyah-classes.index')
            ->with('success', 'Kelas diniyah berhasil diperbarui.');
    }

    public function destroy(SchoolClass $diniyahClass): RedirectResponse
    {
        $diniyahClass->delete();

        return redirect()->route('admin.diniyah-classes.index')
            ->with('success', 'Kelas diniyah berhasil dihapus.');
    }

    private function syncHomeroomTeacherForActivePeriod(SchoolClass $class, ?int $teacherId): void
    {
        $periodId = (int) (AcademicPeriod::query()->active()->value('id') ?? 0);
        if (! $periodId) {
            return;
        }

        if ($teacherId === null) {
            ClassWali::query()
                ->where('class_id', $class->id)
                ->where('period_id', $periodId)
                ->delete();

            return;
        }

        ClassWali::query()->updateOrCreate(
            [
                'class_id' => $class->id,
                'period_id' => $periodId,
            ],
            [
                'teacher_id' => $teacherId,
            ],
        );
    }
}
