<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStudentRequest;
use App\Http\Requests\Admin\UpdateStudentRequest;
use App\Models\Diniyyah\SchoolClass;
use App\Models\ImportRun;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Student::with(['currentClass', 'user'])
            ->when($request->search, fn ($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('full_name', 'ilike', "%{$search}%")
                  ->orWhere('nis', 'ilike', "%{$search}%");
            }))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->class_id, fn ($q, $classId) => $q->where('current_class_id', $classId))
            ->orderBy('full_name');

        $importRunsQuery = ImportRun::query()
            ->with('requestedBy:id,name')
            ->where('type', ImportRun::TYPE_STUDENTS)
            ->when($request->import_uploader_id, fn ($q, $uploaderId) => $q->where('requested_by', $uploaderId))
            ->latest('id');

        $importUploaderIds = ImportRun::query()
            ->where('type', ImportRun::TYPE_STUDENTS)
            ->whereNotNull('requested_by')
            ->when($request->import_uploader_id, fn ($q, $uploaderId) => $q->where('requested_by', $uploaderId))
            ->distinct()
            ->pluck('requested_by');

        $uploaders = User::query()
            ->whereIn('id', $importUploaderIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('admin/students/index', [
            'students' => $query->paginate(15)->withQueryString(),
            'classes' => SchoolClass::orderBy('name')->get(['id', 'name', 'level']),
            'filters' => $request->only(['search', 'status', 'class_id', 'import_uploader_id']),
            'importRuns' => $importRunsQuery->limit(20)->get(),
            'importUploaders' => $uploaders,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/students/create', [
            'classes' => SchoolClass::with('gradeLevel')
                ->orderBy('name')
                ->get(['id', 'name', 'level', 'grade_level_id']),
        ]);
    }

    public function store(StoreStudentRequest $request): RedirectResponse
    {
        Student::create([
            ...$request->validated(),
            'gender' => Student::GENDER_MALE,
            'status' => Student::STATUS_ACTIVE,
        ]);

        return redirect()->route('admin.students.index')
            ->with('success', 'Data santri berhasil ditambahkan.');
    }

    public function show(Student $student): Response
    {
        $student->load(['currentClass.gradeLevel', 'guardians.user', 'user']);

        return Inertia::render('admin/students/show', [
            'student' => $student,
        ]);
    }

    public function edit(Student $student): Response
    {
        return Inertia::render('admin/students/edit', [
            'student' => $student,
            'classes' => SchoolClass::with('gradeLevel')
                ->orderBy('name')
                ->get(['id', 'name', 'level', 'grade_level_id']),
        ]);
    }

    public function update(UpdateStudentRequest $request, Student $student): RedirectResponse
    {
        $validated = $request->validated();
        $targetGender = $validated['gender'] ?? $student->gender;
        $targetClassId = $validated['current_class_id'] ?? $student->current_class_id;

        if ($targetClassId !== null) {
            $targetClass = SchoolClass::query()->find($targetClassId);
            if ($targetClass && ! $targetClass->acceptsStudentGender($targetGender)) {
                return back()
                    ->withErrors([
                        'current_class_id' => "Kelas {$targetClass->name} hanya untuk {$targetClass->student_gender_label}.",
                    ])
                    ->withInput();
            }
        }

        $student->update($validated);

        return redirect()->route('admin.students.show', $student)
            ->with('success', 'Data santri berhasil diperbarui.');
    }

    public function destroy(Student $student): RedirectResponse
    {
        $student->delete();

        return redirect()->route('admin.students.index')
            ->with('success', 'Data santri berhasil dihapus.');
    }
}
