<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStudentRequest;
use App\Http\Requests\Admin\UpdateStudentRequest;
use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\Diniyyah\SchoolClass;
use App\Models\DormAssignment;
use App\Models\DormRoom;
use App\Models\EmProfile;
use App\Models\EnrollmentTingkatSekolah;
use App\Models\ImportRun;
use App\Models\Role;
use App\Models\Student;
use App\Models\TingkatSekolah;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    public function eligibleUsers(Request $request): JsonResponse
    {
        $search = trim((string) $request->string('search'));
        $includeUserId = (int) $request->integer('include_user_id');

        $users = User::query()
            ->whereHas('roles', fn (Builder $roleQuery) => $roleQuery->whereNotIn('name', [Role::SANTRI, Role::ALUMNI]))
            ->when($search !== '', function (Builder $builder) use ($search) {
                $builder->where(function (Builder $inner) use ($search) {
                    $inner->where('name', 'ilike', "%{$search}%")
                        ->orWhere('username', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%");
                });
            })
            ->where(function (Builder $builder) use ($includeUserId) {
                $builder->whereDoesntHave('student');
                if ($includeUserId > 0) {
                    $builder->orWhere('id', $includeUserId);
                }
            })
            ->orderBy('name')
            ->limit(25)
            ->get(['id', 'name', 'email', 'username']);

        return response()->json([
            'data' => $users,
        ]);
    }

    public function index(Request $request): Response
    {
        $displayAcademicYearId = $this->resolveAcademicYearId((int) $request->input('academic_year_id', 0));
        $roomId = (int) $request->input('room_id', 0);
        $tingkatSekolahId = (int) $request->input('tingkat_sekolah_id', 0);

        $query = Student::with([
            'currentClass',
            'user',
            'enrollmentTingkatSekolahs' => fn ($q) => $q
                ->where('academic_year_id', $displayAcademicYearId)
                ->with('tingkatSekolah:id,name,code'),
        ])
            ->when($request->search, fn ($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('full_name', 'ilike', "%{$search}%")
                    ->orWhere('nis', 'ilike', "%{$search}%");
            }))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->class_id, fn ($q, $classId) => $q->where('current_class_id', $classId))
            ->when($roomId > 0, function (Builder $q) use ($roomId, $displayAcademicYearId) {
                $q->whereHas('dormAssignments', function (Builder $inner) use ($roomId, $displayAcademicYearId) {
                    $inner->where('room_id', $roomId)
                        ->activeInAcademicYear($displayAcademicYearId);
                });
            })
            ->when($tingkatSekolahId > 0, function (Builder $q) use ($tingkatSekolahId, $displayAcademicYearId) {
                $q->whereHas('enrollmentTingkatSekolahs', function (Builder $inner) use ($tingkatSekolahId, $displayAcademicYearId) {
                    $inner->where('academic_year_id', $displayAcademicYearId)
                        ->where('tingkat_sekolah_id', $tingkatSekolahId);
                });
            })
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
            'classes' => SchoolClass::orderBy('name')->get(['id', 'name', 'grade_level_id']),
            'tingkatSekolahs' => TingkatSekolah::query()
                ->orderBy('order')
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'group']),
            'academicYears' => AcademicYear::query()
                ->orderByDesc('start_date')
                ->orderByDesc('id')
                ->get(['id', 'name']),
            'selectedAcademicYearId' => $displayAcademicYearId,
            'filters' => $request->only([
                'search',
                'status',
                'class_id',
                'import_uploader_id',
                'room_id',
                'tingkat_sekolah_id',
                'academic_year_id',
            ]),
            'filterContext' => $this->buildStudentFilterContext(
                $request,
                $displayAcademicYearId,
                $roomId,
                $tingkatSekolahId,
            ),
            'importRuns' => $importRunsQuery->limit(20)->get(),
            'importUploaders' => $uploaders,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildStudentFilterContext(
        Request $request,
        int $displayAcademicYearId,
        int $roomId,
        int $tingkatSekolahId,
    ): ?array {
        $academicYearName = AcademicYear::query()->whereKey($displayAcademicYearId)->value('name');

        if ($roomId > 0) {
            $room = DormRoom::query()
                ->with('building:id,name')
                ->find($roomId);
            if ($room) {
                $buildingName = $room->building?->name ?? 'Asrama';

                return [
                    'type' => 'room',
                    'title' => "Anggota Kobong {$room->room_number}",
                    'subtitle' => "{$buildingName} · Tahun ajaran {$academicYearName}",
                    'back_href' => '/admin/asrama',
                    'back_label' => 'Kembali ke Asrama',
                ];
            }
        }

        if ($request->filled('class_id')) {
            $class = SchoolClass::query()->find((int) $request->input('class_id'));
            if ($class) {
                return [
                    'type' => 'class',
                    'title' => "Anggota Kelas {$class->name}",
                    'subtitle' => 'Kelas diniyah · berdasarkan kelas saat ini santri',
                    'back_href' => '/admin/diniyah-classes',
                    'back_label' => 'Kembali ke Kelas Diniyah',
                ];
            }
        }

        if ($tingkatSekolahId > 0) {
            $tingkat = TingkatSekolah::query()->find($tingkatSekolahId);
            if ($tingkat) {
                return [
                    'type' => 'tingkat',
                    'title' => "Anggota Tingkat {$tingkat->name}",
                    'subtitle' => "Enrollment formal · Tahun ajaran {$academicYearName}",
                    'back_href' => '/admin/tingkat-sekolahs',
                    'back_label' => 'Kembali ke Master Tingkat',
                ];
            }
        }

        return null;
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

    public function create(Request $request): Response
    {
        abort_unless($request->user()->hasAnyRole([Role::SUPER_ADMIN, Role::ADMIN_AKADEMIK]), 403, 'Anda tidak memiliki akses untuk menambah data santri.');

        return Inertia::render('admin/students/create', [
            'classes' => SchoolClass::with('gradeLevel')
                ->orderBy('name')
                ->get(['id', 'name', 'grade_level_id']),
        ]);
    }

    public function store(StoreStudentRequest $request): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole([Role::SUPER_ADMIN, Role::ADMIN_AKADEMIK]), 403, 'Anda tidak memiliki akses untuk menambah data santri.');

        Student::create([
            ...$request->validated(),
            'gender' => $request->gender ?? Student::GENDER_MALE,
            'status' => Student::STATUS_ACTIVE,
        ]);

        return redirect()->route('admin.students.index')
            ->with('success', 'Data santri berhasil ditambahkan.');
    }

    public function show(Student $student): Response
    {
        $student->load(['currentClass.gradeLevel', 'guardians.user', 'user', 'emisProfile']);
        $student->setAttribute('em_profile', $student->emProfilePayloadForFrontend());

        return Inertia::render('admin/students/show', [
            'student' => $student,
        ]);
    }

    public function edit(Request $request, Student $student): Response
    {
        abort_unless($request->user()->isSuperAdmin(), 403, 'Hanya Super Admin yang dapat mengedit data santri.');

        $student->load(['user', 'emisProfile']);
        $student->setAttribute('em_profile', $student->emProfilePayloadForFrontend());

        return Inertia::render('admin/students/edit', [
            'student' => $student,
            'classes' => SchoolClass::with('gradeLevel')
                ->orderBy('name')
                ->get(['id', 'name', 'grade_level_id']),
        ]);
    }

    public function update(UpdateStudentRequest $request, Student $student): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403, 'Hanya Super Admin yang dapat mengubah data santri.');
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

        $emProfileData = $request->input('em_profile', []);
        unset($validated['em_profile']);

        $student->update($validated);

        if (is_array($emProfileData) && count($emProfileData) > 0) {
            $student->loadMissing('emisProfile');
            $current = $student->emProfilePayload();
            $merged = array_replace_recursive($current, $emProfileData);
            $merged = $student->withComputedNismInEmProfilePayload($merged);
            $attributes = EmProfile::fromPayload($merged);
            $student->emisProfile()->updateOrCreate([], $attributes);
            $student->forceFill(['em_profile' => $merged])->save();
        }

        return redirect()->route('admin.students.show', $student)
            ->with('success', 'Data santri berhasil diperbarui.');
    }

    public function destroy(Request $request, Student $student): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole([Role::SUPER_ADMIN, Role::ADMIN_AKADEMIK]), 403, 'Anda tidak memiliki akses untuk menghapus data santri.');

        $student->delete();

        return redirect()->route('admin.students.index')
            ->with('success', 'Data santri berhasil dihapus.');
    }
}
