<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\Diniyyah\AssessmentComponent;
use App\Models\Diniyyah\ClassWali;
use App\Models\Diniyyah\SchoolClass;
use App\Models\Diniyyah\Score;
use App\Models\Diniyyah\StudentClassEnrollment;
use App\Models\Diniyyah\Subject;
use App\Models\Diniyyah\TeacherAssignment;
use App\Models\DormAssignment;
use App\Models\DormBuilding;
use App\Models\DormRoom;
use App\Models\EmProfile;
use App\Models\Guardian;
use App\Models\LeavePermission;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentViolation;
use App\Models\User;
use App\Models\ViolationSummary;
use App\Models\ViolationType;
use App\Notifications\LeaveStatusChangedNotification;
use App\Notifications\ViolationRecordedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $totalStudents = Student::where('status', Student::STATUS_ACTIVE)->count();
        $totalClasses = SchoolClass::count();
        $totalGuru = User::whereHas('roles', fn ($q) => $q->where('name', Role::GURU))->where('is_active', true)->count();
        $totalMusyrif = User::whereHas('roles', fn ($q) => $q->where('name', Role::MUSYRIF))->where('is_active', true)->count();

        $recentViolations = StudentViolation::with(['student:id,full_name,nis', 'violationType:id,name,category'])
            ->latest('date')->limit(5)->get();

        $pendingLeaves = LeavePermission::where('status', 'pending')
            ->with('student:id,full_name,nis')
            ->latest()->limit(5)->get();

        $classCounts = SchoolClass::withCount(['students' => fn ($q) => $q->where('status', Student::STATUS_ACTIVE)])
            ->orderBy('order')->orderBy('name')
            ->get(['id', 'name', 'grade_level_id']);

        $incompleteStudentProfiles = Student::query()
            ->where('status', Student::STATUS_ACTIVE)
            ->where(function ($q) {
                $q->whereNull('nik')
                    ->orWhereNull('birth_date')
                    ->orWhereNull('address');
            })->count();

        $totalWali = Guardian::query()->count();
        $mutationsThisYear = Student::query()
            ->whereYear('updated_at', now()->year)
            ->whereIn('status', [Student::STATUS_ALUMNI, Student::STATUS_KELUAR, Student::STATUS_WAFAT])
            ->count();

        $recentActivity = $this->buildAdminRecentActivity(
            $recentViolations,
            $pendingLeaves,
        );

        return response()->json([
            'stats' => array_merge(compact('totalStudents', 'totalClasses', 'totalGuru', 'totalMusyrif'), [
                'incompleteStudentProfiles' => $incompleteStudentProfiles,
                'totalWali' => $totalWali,
                'mutationsThisYear' => $mutationsThisYear,
            ]),
            'recentViolations' => $recentViolations,
            'pendingLeaves' => $pendingLeaves,
            'classCounts' => $classCounts,
            'recentActivity' => $recentActivity,
        ]);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, StudentViolation>  $recentViolations
     * @param  \Illuminate\Database\Eloquent\Collection<int, LeavePermission>  $pendingLeaves
     * @return list<array{message: string, occurred_at: string|null, kind: string}>
     */
    private function buildAdminRecentActivity($recentViolations, $pendingLeaves): array
    {
        $rows = collect();

        foreach ($recentViolations as $v) {
            $student = $v->student;
            $name = $student?->full_name ?? 'Santri';
            $typeName = $v->violationType?->name ?? 'Pelanggaran';
            $rows->push([
                'message' => "Pelanggaran: {$name} — {$typeName}",
                'occurred_at' => $v->date?->toIso8601String(),
                'kind' => 'warning',
            ]);
        }

        foreach ($pendingLeaves as $p) {
            $student = $p->student;
            $name = $student?->full_name ?? 'Santri';
            $rows->push([
                'message' => "Izin menunggu persetujuan: {$name}",
                'occurred_at' => $p->created_at?->toIso8601String(),
                'kind' => 'leave',
            ]);
        }

        return $rows
            ->sortByDesc(fn (array $r) => $r['occurred_at'] ?? '')
            ->values()
            ->take(8)
            ->all();
    }

    public function classes(Request $request): JsonResponse
    {
        $activePeriodId = (int) (AcademicPeriod::query()->active()->value('id') ?? 0);

        $classes = SchoolClass::query()
            ->withCount([
                'students as students_count' => fn ($q) => $q->where('status', Student::STATUS_ACTIVE),
                'students as male_students_count' => fn ($q) => $q
                    ->where('status', Student::STATUS_ACTIVE)
                    ->where('gender', Student::GENDER_MALE),
                'students as female_students_count' => fn ($q) => $q
                    ->where('status', Student::STATUS_ACTIVE)
                    ->where('gender', Student::GENDER_FEMALE),
            ])
            ->with([
                'walis' => fn ($q) => $q
                    ->when($activePeriodId, fn ($sq) => $sq->where('period_id', $activePeriodId))
                    ->with('teacher:id,name')
                    ->orderByDesc('id'),
            ])
            ->orderBy('order')
            ->orderBy('name')
            ->get();

        $classIds = $classes->pluck('id')->all();
        $teacherAssignments = TeacherAssignment::query()
            ->when(
                $activePeriodId,
                fn ($q) => $q->where('period_id', $activePeriodId),
                fn ($q) => $q->whereRaw('0 = 1')
            )
            ->when(
                ! empty($classIds),
                fn ($q) => $q->whereIn('class_id', $classIds),
                fn ($q) => $q->whereRaw('0 = 1')
            )
            ->get(['class_id', 'teacher_id']);
        $teachersCountByClass = $teacherAssignments
            ->groupBy('class_id')
            ->map(fn ($rows) => (int) $rows->pluck('teacher_id')->unique()->count());
        $totalUniqueTeachers = (int) $teacherAssignments->pluck('teacher_id')->unique()->count();

        $classes = $classes
            ->map(function (SchoolClass $class) use ($teachersCountByClass) {
                $wali = $class->walis->first();
                $teachersCount = (int) ($teachersCountByClass->get($class->id) ?? 0);

                return array_merge($class->toArray(), [
                    'wali_kelas' => $wali?->teacher?->name,
                    'wali_kelas_name' => $wali?->teacher?->name,
                    'wali_kelas_teacher_id' => $wali?->teacher_id,
                    'teachers_count' => $teachersCount,
                ]);
            })
            ->values();

        return response()->json([
            'classes' => $classes,
            'summary' => [
                'total_unique_teachers' => $totalUniqueTeachers,
            ],
        ]);
    }

    public function classHomeroomCandidates(Request $request, SchoolClass $class): JsonResponse
    {
        $activePeriodId = (int) (AcademicPeriod::query()->active()->value('id') ?? 0);
        if (! $activePeriodId) {
            return response()->json([
                'message' => 'Belum ada periode akademik aktif.',
                'teachers' => [],
            ], 422);
        }

        $teachers = User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->where('name', Role::GURU))
            ->orderBy('name')
            ->get(['id', 'name']);

        $current = ClassWali::query()
            ->where('class_id', $class->id)
            ->where('period_id', $activePeriodId)
            ->first(['teacher_id']);

        return response()->json([
            'period_id' => $activePeriodId,
            'teachers' => $teachers,
            'current_teacher_id' => $current?->teacher_id,
        ]);
    }

    public function setClassHomeroomTeacher(Request $request, SchoolClass $class): JsonResponse
    {
        $activePeriodId = (int) (AcademicPeriod::query()->active()->value('id') ?? 0);
        if (! $activePeriodId) {
            return response()->json([
                'message' => 'Belum ada periode akademik aktif.',
            ], 422);
        }

        $validated = $request->validate([
            'teacher_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $teacher = User::query()
            ->where('id', (int) $validated['teacher_id'])
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->where('name', Role::GURU))
            ->first();
        if (! $teacher) {
            return response()->json([
                'message' => 'Guru wali kelas tidak valid atau tidak aktif.',
            ], 422);
        }

        $row = ClassWali::query()->updateOrCreate(
            [
                'class_id' => $class->id,
                'period_id' => $activePeriodId,
            ],
            [
                'teacher_id' => $teacher->id,
            ]
        );

        return response()->json([
            'message' => 'Wali kelas berhasil ditetapkan.',
            'class_id' => $class->id,
            'period_id' => $activePeriodId,
            'wali_kelas' => [
                'teacher_id' => $row->teacher_id,
                'teacher_name' => $teacher->name,
            ],
        ]);
    }

    public function updateClassSettings(Request $request, SchoolClass $class): JsonResponse
    {
        $activePeriodId = (int) (AcademicPeriod::query()->active()->value('id') ?? 0);
        if (! $activePeriodId) {
            return response()->json([
                'message' => 'Belum ada periode akademik aktif.',
            ], 422);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'grade_level_id' => ['nullable', 'exists:grade_levels,id'],
            'student_gender' => ['nullable', 'string', 'size:1', Rule::in(SchoolClass::STUDENT_GENDERS)],
            'wali_kelas_teacher_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $waliTeacher = null;
        if (array_key_exists('wali_kelas_teacher_id', $validated) && $validated['wali_kelas_teacher_id'] !== null) {
            $waliTeacher = User::query()
                ->where('id', (int) $validated['wali_kelas_teacher_id'])
                ->where('is_active', true)
                ->whereHas('roles', fn ($q) => $q->where('name', Role::GURU))
                ->first();
            if (! $waliTeacher) {
                return response()->json([
                    'message' => 'Guru wali kelas tidak valid atau tidak aktif.',
                ], 422);
            }
        }

        $class->update([
            'name' => $validated['name'],
            'grade_level_id' => $validated['grade_level_id'] ?? null,
            'student_gender' => $validated['student_gender'] ?? null,
        ]);

        if (array_key_exists('wali_kelas_teacher_id', $validated)) {
            if ($waliTeacher) {
                ClassWali::query()->updateOrCreate(
                    [
                        'class_id' => $class->id,
                        'period_id' => $activePeriodId,
                    ],
                    [
                        'teacher_id' => $waliTeacher->id,
                    ]
                );
            } else {
                ClassWali::query()
                    ->where('class_id', $class->id)
                    ->where('period_id', $activePeriodId)
                    ->delete();
            }
        }

        $currentWali = ClassWali::query()
            ->where('class_id', $class->id)
            ->where('period_id', $activePeriodId)
            ->with('teacher:id,name')
            ->first();

        return response()->json([
            'message' => 'Pengaturan kelas berhasil diperbarui.',
            'class' => array_merge($class->fresh()->toArray(), [
                'wali_kelas' => $currentWali?->teacher?->name,
                'wali_kelas_teacher_id' => $currentWali?->teacher_id,
            ]),
        ]);
    }

    public function classTeachers(Request $request, SchoolClass $class): JsonResponse
    {
        $activePeriodId = (int) (AcademicPeriod::query()->active()->value('id') ?? 0);
        if (! $activePeriodId) {
            return response()->json([
                'message' => 'Belum ada periode akademik aktif.',
                'teachers' => [],
            ], 422);
        }

        $assignments = TeacherAssignment::query()
            ->where('class_id', $class->id)
            ->where('period_id', $activePeriodId)
            ->with([
                'teacher:id,name',
                'subject:id,name',
            ])
            ->orderBy('teacher_id')
            ->get(['teacher_id', 'subject_id', 'class_id', 'period_id']);
        $waliTeacherId = ClassWali::query()
            ->where('class_id', $class->id)
            ->where('period_id', $activePeriodId)
            ->value('teacher_id');

        $teachers = $assignments
            ->groupBy('teacher_id')
            ->map(function ($rows, $teacherId) use ($waliTeacherId) {
                $teacher = $rows->first()?->teacher;
                $subjects = $rows
                    ->map(fn (TeacherAssignment $a) => $a->subject)
                    ->filter()
                    ->unique('id')
                    ->values()
                    ->map(fn (Subject $s) => ['id' => $s->id, 'name' => $s->name])
                    ->all();

                return [
                    'teacher_id' => (int) $teacherId,
                    'teacher_name' => $teacher?->name ?? '-',
                    'is_wali_kelas' => $waliTeacherId !== null && (int) $waliTeacherId === (int) $teacherId,
                    'subjects' => $subjects,
                    'subjects_count' => count($subjects),
                    'target_jam_total' => (int) $rows->sum(fn (TeacherAssignment $a) => (int) ($a->target_jam ?? 0)),
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'class' => [
                'id' => $class->id,
                'name' => $class->name,
            ],
            'period_id' => $activePeriodId,
            'teachers' => $teachers,
            'total_teachers' => count($teachers),
        ]);
    }

    /**
     * Daftar kobong / gedung asrama untuk filter admin (mis. dropdown).
     */
    public function dormBuildings(Request $request): JsonResponse
    {
        $buildings = DormBuilding::orderBy('name')->get(['id', 'name']);

        return response()->json(['dorm_buildings' => $buildings]);
    }

    public function dormManagement(Request $request): JsonResponse
    {
        $buildingId = (int) $request->input('building_id', 0);
        $buildingId = $buildingId > 0 ? $buildingId : null;

        $buildings = DormBuilding::withCount('rooms')
            ->orderBy('name')
            ->get(['id', 'name', 'description']);

        $rooms = \App\Models\DormRoom::query()
            ->with([
                'building:id,name',
                'musyrif.user:id,name',
            ])
            ->withCount(['activeAssignments as occupied_count'])
            ->when($buildingId, fn ($q) => $q->where('building_id', $buildingId))
            ->orderBy('building_id')
            ->orderBy('room_number')
            ->get(['id', 'building_id', 'room_number', 'capacity', 'floor']);

        $roomIds = $rooms->pluck('id');
        $assignmentsByRoom = DormAssignment::query()
            ->whereNull('checkout_date')
            ->when($roomIds->isNotEmpty(), fn ($q) => $q->whereIn('room_id', $roomIds->all()), fn ($q) => $q->whereRaw('0 = 1'))
            ->get(['room_id', 'student_id'])
            ->groupBy('room_id');

        $today = now()->toDateString();
        $awayStudentIdSet = LeavePermission::query()
            ->where('status', LeavePermission::STATUS_APPROVED)
            ->whereDate('leave_date', '<=', $today)
            ->whereDate('return_date', '>=', $today)
            ->pluck('student_id')
            ->unique()
            ->flip()
            ->all();

        $roomsPayload = $rooms->map(function (\App\Models\DormRoom $room) use ($assignmentsByRoom, $awayStudentIdSet) {
            $occupied = (int) $room->occupied_count;
            $capacity = (int) $room->capacity;
            $emptySlots = max(0, $capacity - $occupied);
            $memberIds = $assignmentsByRoom->get($room->id, collect())->pluck('student_id');
            $awayCount = (int) $memberIds->filter(function ($id) use ($awayStudentIdSet) {
                $id = (int) $id;

                return isset($awayStudentIdSet[$id]);
            })->count();
            $presentCount = max(0, $occupied - $awayCount);
            $fillPercent = $capacity > 0 ? (int) round(100 * $occupied / $capacity) : 0;
            $ketua = $room->musyrif?->user?->name;

            return array_merge(
                $room->makeHidden(['musyrif'])->toArray(),
                [
                    'ketua_kobong' => $ketua,
                    'empty_slots' => $emptySlots,
                    'away_count' => $awayCount,
                    'present_count' => $presentCount,
                    'capacity_fill_percent' => $fillPercent,
                ]
            );
        })->values();

        return response()->json([
            'buildings' => $buildings,
            'rooms' => $roomsPayload,
            'filters' => [
                'building_id' => $buildingId,
            ],
        ]);
    }

    public function storeDormBuilding(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:dorm_buildings,name'],
            'description' => ['nullable', 'string'],
        ]);

        $building = DormBuilding::create($validated);

        return response()->json([
            'message' => 'Asrama berhasil ditambahkan.',
            'building' => $building,
        ], 201);
    }

    public function updateDormBuilding(Request $request, DormBuilding $building): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('dorm_buildings', 'name')->ignore($building->id)],
            'description' => ['nullable', 'string'],
        ]);

        $building->update($validated);

        return response()->json([
            'message' => 'Asrama berhasil diperbarui.',
            'building' => $building->fresh(),
        ]);
    }

    public function destroyDormBuilding(DormBuilding $building): JsonResponse
    {
        $hasActiveAssignments = $building->rooms()
            ->whereHas('activeAssignments')
            ->exists();
        if ($hasActiveAssignments) {
            return response()->json([
                'message' => 'Asrama tidak bisa dihapus karena masih ada kobong terisi santri aktif.',
            ], 422);
        }

        $building->delete();

        return response()->json([
            'message' => 'Asrama berhasil dihapus.',
        ]);
    }

    public function storeDormRoom(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'building_id' => ['required', 'exists:dorm_buildings,id'],
            'room_number' => ['required', 'string', 'max:32'],
            'capacity' => ['required', 'integer', 'min:1', 'max:200'],
            'floor' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $exists = \App\Models\DormRoom::query()
            ->where('building_id', $validated['building_id'])
            ->whereRaw('LOWER(room_number) = ?', [mb_strtolower($validated['room_number'])])
            ->exists();
        if ($exists) {
            return response()->json([
                'message' => 'Nomor kobong sudah ada di asrama ini.',
            ], 422);
        }

        $room = \App\Models\DormRoom::create($validated);
        $room->load('building:id,name')->loadCount(['activeAssignments as occupied_count']);

        return response()->json([
            'message' => 'Kobong berhasil ditambahkan.',
            'room' => $room,
        ], 201);
    }

    public function updateDormRoom(Request $request, \App\Models\DormRoom $room): JsonResponse
    {
        $validated = $request->validate([
            'building_id' => ['required', 'exists:dorm_buildings,id'],
            'room_number' => ['required', 'string', 'max:32'],
            'capacity' => ['required', 'integer', 'min:1', 'max:200'],
            'floor' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $exists = \App\Models\DormRoom::query()
            ->where('id', '!=', $room->id)
            ->where('building_id', $validated['building_id'])
            ->whereRaw('LOWER(room_number) = ?', [mb_strtolower($validated['room_number'])])
            ->exists();
        if ($exists) {
            return response()->json([
                'message' => 'Nomor kobong sudah ada di asrama ini.',
            ], 422);
        }

        $occupied = $room->activeAssignments()->count();
        if ($validated['capacity'] < $occupied) {
            return response()->json([
                'message' => "Kapasitas tidak boleh kurang dari penghuni aktif ({$occupied}).",
            ], 422);
        }

        $room->update($validated);
        $room->load('building:id,name')->loadCount(['activeAssignments as occupied_count']);

        return response()->json([
            'message' => 'Kobong berhasil diperbarui.',
            'room' => $room,
        ]);
    }

    public function destroyDormRoom(\App\Models\DormRoom $room): JsonResponse
    {
        if ($room->activeAssignments()->exists()) {
            return response()->json([
                'message' => 'Kobong tidak bisa dihapus karena masih ada santri aktif.',
            ], 422);
        }

        $room->delete();

        return response()->json([
            'message' => 'Kobong berhasil dihapus.',
        ]);
    }

    /**
     * Anggota aktif + ringkasan kobong (Figma: Detail Kobong / anggota santri).
     */
    public function dormRoomMembers(DormRoom $room): JsonResponse
    {
        $room->load([
            'building:id,name',
            'musyrif.user:id,name',
        ]);
        $room->loadCount(['activeAssignments as occupied_count']);

        $assignments = DormAssignment::query()
            ->where('room_id', $room->id)
            ->whereNull('checkout_date')
            ->with([
                'student' => fn ($q) => $q
                    ->select('id', 'user_id', 'full_name', 'nik', 'birth_date', 'address', 'photo', 'gender', 'current_class_id')
                    ->with('currentClass:id,name,grade_level_id')
                    ->with('emisProfile'),
            ])
            ->get();

        $members = $assignments
            ->map(fn (DormAssignment $a) => $a->student)
            ->filter()
            ->unique('id')
            ->values();

        $memberIds = $members->pluck('id');
        $today = now()->toDateString();
        $awayStudentIdSet = $memberIds->isEmpty()
            ? []
            : LeavePermission::query()
                ->where('status', LeavePermission::STATUS_APPROVED)
                ->whereDate('leave_date', '<=', $today)
                ->whereDate('return_date', '>=', $today)
                ->whereIn('student_id', $memberIds->all())
                ->pluck('student_id')
                ->unique()
                ->flip()
                ->all();

        $occupied = (int) $room->occupied_count;
        $capacity = (int) $room->capacity;
        $emptySlots = max(0, $capacity - $occupied);
        $awayCount = (int) $members->filter(function (Student $s) use ($awayStudentIdSet) {
            return isset($awayStudentIdSet[$s->id]);
        })->count();
        $presentCount = max(0, $occupied - $awayCount);
        $fillPercent = $capacity > 0 ? (int) round(100 * $occupied / $capacity) : 0;
        $ketuaName = $room->musyrif?->user?->name;
        $musyrifUserId = $room->musyrif?->user_id;

        $ketuaStudent = null;
        if ($musyrifUserId) {
            $ketuaStudent = $members->first(fn (Student $s) => (int) $s->user_id === (int) $musyrifUserId);
        }

        $byName = $members->sortBy('full_name', SORT_NATURAL | SORT_FLAG_CASE);
        $entries = [];

        if ($room->relationLoaded('musyrif') && $room->musyrif?->user && $ketuaStudent === null) {
            $entries[] = [
                'entry_type' => 'musyrif',
                'user_id' => $room->musyrif->user_id,
                'full_name' => $room->musyrif->user->name,
                'role_line' => 'Ketua Kobong',
                'class_label' => null,
                'profile_status' => null,
                'student_id' => null,
                'photo_url' => null,
            ];
        }

        if ($ketuaStudent !== null) {
            $entries[] = $this->dormRoomMemberRow($ketuaStudent, true);
        }

        foreach ($byName as $s) {
            if ($ketuaStudent !== null && (int) $s->id === (int) $ketuaStudent->id) {
                continue;
            }
            $entries[] = $this->dormRoomMemberRow($s, false);
        }

        $roomPayload = array_merge(
            $room->makeHidden(['musyrif'])->toArray(),
            [
                'display_title' => $this->dormRoomDisplayTitle($room),
                'ketua_kobong' => $ketuaName,
                'empty_slots' => $emptySlots,
                'away_count' => $awayCount,
                'present_count' => $presentCount,
                'capacity_fill_percent' => $fillPercent,
            ]
        );

        return response()->json([
            'room' => $roomPayload,
            'entries' => $entries,
        ]);
    }

    public function dormRoomAssignableStudents(Request $request, DormRoom $room): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));
        $includeAssigned = filter_var($request->input('include_assigned', false), FILTER_VALIDATE_BOOL);
        $limit = (int) $request->input('limit', 50);
        $limit = max(10, min(200, $limit));

        $activeAssignments = DormAssignment::query()
            ->active()
            ->with(['room:id,room_number,building_id', 'room.building:id,name'])
            ->get(['student_id', 'room_id'])
            ->keyBy('student_id');

        $currentRoomStudentIds = DormAssignment::query()
            ->active()
            ->where('room_id', $room->id)
            ->pluck('student_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $currentRoomLookup = array_flip($currentRoomStudentIds);

        $students = Student::query()
            ->where('status', Student::STATUS_ACTIVE)
            ->when($search !== '', function ($q) use ($search) {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $q->where(function ($sq) use ($term) {
                    $sq->where('full_name', 'like', $term)
                        ->orWhere('nis', 'like', $term);
                });
            })
            ->orderBy('full_name')
            ->limit($limit)
            ->get(['id', 'user_id', 'full_name', 'nis', 'gender', 'current_class_id'])
            ->load('currentClass:id,name');

        $payload = $students->map(function (Student $student) use ($activeAssignments, $currentRoomLookup, $includeAssigned) {
            $assignment = $activeAssignments->get($student->id);
            $isAssigned = $assignment !== null;
            $isInCurrentRoom = isset($currentRoomLookup[$student->id]);

            if (! $includeAssigned && $isAssigned && ! $isInCurrentRoom) {
                return null;
            }

            $currentRoomLabel = null;
            if ($assignment?->room) {
                $buildingName = (string) ($assignment->room->building?->name ?? '');
                $short = ltrim(preg_replace('/^asrama\.?\s*/i', '', $buildingName) ?? '');
                $currentRoomLabel = 'Kobong '.$assignment->room->room_number.($short !== '' ? " {$short}" : '');
            }

            return [
                'student_id' => $student->id,
                'full_name' => $student->full_name,
                'nis' => $student->nis,
                'gender' => $student->gender,
                'class_label' => $student->currentClass?->name,
                'is_assigned' => $isAssigned,
                'is_in_current_room' => $isInCurrentRoom,
                'current_room_label' => $currentRoomLabel,
            ];
        })->filter()->values();

        return response()->json([
            'students' => $payload,
            'room_id' => $room->id,
            'filters' => [
                'search' => $search,
                'include_assigned' => $includeAssigned,
            ],
        ]);
    }

    public function storeDormRoomMembers(Request $request, DormRoom $room): JsonResponse
    {
        $validated = $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['required', 'integer', 'distinct', 'exists:students,id'],
            'move_existing' => ['nullable', 'boolean'],
            'checkin_date' => ['nullable', 'date'],
        ]);

        $studentIds = collect($validated['student_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $moveExisting = (bool) ($validated['move_existing'] ?? false);
        $checkinDate = $validated['checkin_date'] ?? now()->toDateString();

        $students = Student::query()
            ->whereIn('id', $studentIds->all())
            ->where('status', Student::STATUS_ACTIVE)
            ->pluck('id');
        if ($students->count() !== $studentIds->count()) {
            return response()->json([
                'message' => 'Hanya santri aktif yang dapat ditambahkan ke kobong.',
            ], 422);
        }

        $activeExisting = DormAssignment::query()
            ->active()
            ->whereIn('student_id', $studentIds->all())
            ->get(['id', 'student_id', 'room_id']);

        $blocked = $activeExisting
            ->filter(fn (DormAssignment $assignment) => (int) $assignment->room_id !== (int) $room->id)
            ->pluck('student_id')
            ->unique()
            ->values();

        if ($blocked->isNotEmpty() && ! $moveExisting) {
            return response()->json([
                'message' => 'Sebagian santri sudah terdaftar di kobong lain. Aktifkan opsi pindah kobong untuk melanjutkan.',
                'blocked_student_ids' => $blocked,
            ], 422);
        }

        $alreadyInRoomIds = $activeExisting
            ->filter(fn (DormAssignment $assignment) => (int) $assignment->room_id === (int) $room->id)
            ->pluck('student_id')
            ->unique()
            ->values();

        $incomingNewIds = $studentIds
            ->reject(fn ($id) => $alreadyInRoomIds->contains($id))
            ->values();

        $occupied = $room->activeAssignments()->count();
        $availableSlots = max(0, (int) $room->capacity - $occupied);
        if ($incomingNewIds->count() > $availableSlots) {
            return response()->json([
                'message' => "Kapasitas kobong tidak cukup. Slot tersedia {$availableSlots}, diminta {$incomingNewIds->count()}.",
            ], 422);
        }

        DB::transaction(function () use ($incomingNewIds, $moveExisting, $room, $checkinDate) {
            if ($incomingNewIds->isEmpty()) {
                return;
            }

            if ($moveExisting) {
                DormAssignment::query()
                    ->active()
                    ->whereIn('student_id', $incomingNewIds->all())
                    ->update(['checkout_date' => now()->toDateString()]);
            }

            $rows = $incomingNewIds->map(fn ($studentId) => [
                'student_id' => (int) $studentId,
                'room_id' => $room->id,
                'checkin_date' => $checkinDate,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();

            DormAssignment::query()->insert($rows);
        });

        return response()->json([
            'message' => 'Anggota kobong berhasil diperbarui.',
            'assigned_count' => $incomingNewIds->count(),
            'already_member_count' => $alreadyInRoomIds->count(),
        ]);
    }

    public function setDormRoomLeader(Request $request, DormRoom $room): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
        ]);

        $student = Student::query()->findOrFail((int) $validated['student_id']);
        $isMember = DormAssignment::query()
            ->active()
            ->where('room_id', $room->id)
            ->where('student_id', $student->id)
            ->exists();
        if (! $isMember) {
            return response()->json([
                'message' => 'Ketua kobong harus dipilih dari anggota kobong aktif.',
            ], 422);
        }
        if (! $student->user_id) {
            return response()->json([
                'message' => 'Santri terpilih tidak memiliki akun user.',
            ], 422);
        }

        Musyrif::query()->updateOrCreate(
            ['assigned_room_id' => $room->id],
            ['user_id' => (int) $student->user_id]
        );

        return response()->json([
            'message' => 'Ketua kobong berhasil ditetapkan.',
            'leader' => [
                'student_id' => $student->id,
                'user_id' => $student->user_id,
                'full_name' => $student->full_name,
            ],
        ]);
    }

    private function dormRoomDisplayTitle(DormRoom $room): string
    {
        $name = (string) ($room->building?->name ?? '');
        $short = ltrim(preg_replace('/^asrama\.?\s*/i', '', $name) ?? '');

        return 'Kobong '.$room->room_number.($short !== '' ? ' '.$short : '');
    }

    private function dormRoomProfileStatus(Student $student): string
    {
        $student->loadMissing('emisProfile');
        if ($student->emisProfile?->isDataComplete() === true) {
            return 'lengkap';
        }

        if ($student->emisProfile !== null) {
            return 'perlu_update';
        }

        return 'belum_lengkap';
    }

    private function dormRoomPhotoUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }

    /**
     * @return array<string, mixed>
     */
    private function dormRoomMemberRow(Student $student, bool $isKetua): array
    {
        $classLabel = $student->currentClass
            ? 'Kelas : '.$student->currentClass->name
            : 'Kelas : —';

        $roleLine = $isKetua
            ? 'Ketua Kobong'
            : ($student->gender === Student::GENDER_FEMALE ? 'Santriyyah' : 'Anggota');

        return [
            'entry_type' => 'student',
            'student_id' => $student->id,
            'full_name' => $student->full_name,
            'role_line' => $roleLine,
            'class_label' => $classLabel,
            'profile_status' => $this->dormRoomProfileStatus($student),
            'gender' => $student->gender,
            'photo_url' => $this->dormRoomPhotoUrl($student->photo),
        ];
    }

    public function studentFormOptions(Request $request): JsonResponse
    {
        $classes = SchoolClass::query()->orderBy('order')->orderBy('name')->get(['id', 'name', 'grade_level_id']);

        $buildings = DormBuilding::with([
            'rooms' => fn ($q) => $q
                ->withCount(['activeAssignments as occupied_count'])
                ->orderBy('room_number')
                ->select(['id', 'building_id', 'room_number', 'capacity']),
        ])->orderBy('name')->get(['id', 'name']);

        return response()->json([
            'classes' => $classes,
            'dorm_buildings' => $buildings,
            'guardian_relationships' => Guardian::RELATIONSHIPS,
            'wali_sources' => ['manual', 'ayah', 'ibu'],
        ]);
    }

    public function semesters(Request $request): JsonResponse
    {
        $semesters = Semester::with('academicYear:id,name')->orderByDesc('id')->get();

        return response()->json(['semesters' => $semesters]);
    }

    public function students(Request $request): JsonResponse
    {
        $search = $request->input('search');
        $gender = $request->input('gender');
        $status = $request->input('status', Student::STATUS_ACTIVE);

        $query = Student::with(['currentClass:id,name', 'emisProfile'])
            ->when($request->filled('class_id'), fn ($q) => $q->where('current_class_id', (int) $request->class_id))
            ->when($request->filled('dorm_building_id'), function ($q) use ($request) {
                $bid = (int) $request->dorm_building_id;
                $q->whereHas(
                    'currentDormAssignment.room',
                    fn ($r) => $r->where('building_id', $bid)
                );
            })
            ->when($search !== null && $search !== '', function ($q) use ($search) {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $q->where(function ($q2) use ($term) {
                    $q2->where('full_name', 'like', $term)
                        ->orWhere('nis', 'like', $term);
                });
            })
            ->when(
                in_array($gender, [Student::GENDER_MALE, Student::GENDER_FEMALE], true),
                fn ($q) => $q->where('gender', $gender)
            );

        if ($status === 'all') {
            // tanpa filter status
        } elseif (in_array($status, Student::STATUSES, true)) {
            $query->where('status', $status);
        } else {
            $query->where('status', Student::STATUS_ACTIVE);
        }

        $completeness = $request->input('completeness');
        if (in_array($completeness, ['personal', 'parents', 'address'], true)) {
            $query->where(function ($q) {
                $q->whereDoesntHave('emisProfile')
                    ->orWhereHas('emisProfile', fn ($em) => $em->incompleteData());
            });
        }

        $query->orderBy('full_name');

        $students = $query->paginate(15)->withQueryString();

        $students->getCollection()->transform(function (Student $student) {
            $payload = $student->toArray();
            $payload['em_profile_complete'] = $student->emisProfile?->isDataComplete() ?? ! empty($student->emProfilePayload());

            return $payload;
        });

        return response()->json([
            'students' => $students,
            'filters' => $request->only(['class_id', 'search', 'gender', 'status', 'dorm_building_id', 'completeness']),
        ]);
    }

    public function storeStudent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student' => ['required', 'array'],
            'student.full_name' => ['required', 'string', 'max:255'],
            'student.nis' => ['required', 'string', 'max:32', 'unique:students,nis'],
            'student.nik' => ['nullable', 'string', 'max:32'],
            'student.nisn' => ['nullable', 'string', 'max:32'],
            'student.nism' => ['nullable', 'string', 'max:32'],
            'student.gender' => ['required', Rule::in([Student::GENDER_MALE, Student::GENDER_FEMALE])],
            'student.birth_place' => ['nullable', 'string', 'max:120'],
            'student.birth_date' => ['nullable', 'date'],
            'student.address' => ['nullable', 'string', 'max:2000'],
            'student.kewarganegaraan' => ['nullable', 'string', 'max:32'],
            'student.no_hp' => ['nullable', 'string', 'max:32'],
            'student.email' => ['nullable', 'email', 'max:255'],
            'student.agama' => ['nullable', 'string', 'max:64'],
            'student.status_mukim' => ['nullable', 'string', 'max:100'],
            'student.status_tempat_tinggal' => ['nullable', 'string', 'max:100'],
            'student.admission_year' => ['nullable', 'integer', 'min:2000', 'max:2099'],
            'parents' => ['required', 'array'],
            'parents.wali_data_source' => ['nullable', Rule::in(['manual', 'ayah', 'ibu'])],
            'parents.ayah' => ['required', 'array'],
            'parents.ibu' => ['required', 'array'],
            'parents.wali' => ['nullable', 'array'],
            'parents.ayah.full_name' => ['required', 'string', 'max:255'],
            'parents.ibu.full_name' => ['required', 'string', 'max:255'],
            'parents.wali.full_name' => ['required_if:parents.wali_data_source,manual', 'nullable', 'string', 'max:255'],
            'addresses' => ['required', 'array'],
            'addresses.ayah' => ['required', 'array'],
            'addresses.ibu' => ['required', 'array'],
            'addresses.wali' => ['required', 'array'],
            'addresses.santri' => ['required', 'array'],
            'addresses.ibu_sama_dengan_ayah' => ['nullable', 'boolean'],
            'addresses.wali_sama_dengan_ayah' => ['nullable', 'boolean'],
            'addresses.wali_sama_dengan_ibu' => ['nullable', 'boolean'],
            'placement' => ['required', 'array'],
            'placement.class_id' => ['nullable', 'exists:classes,id'],
            'placement.dorm_room_id' => ['nullable', 'exists:dorm_rooms,id'],
            'placement.checkin_date' => ['nullable', 'date'],
            'placement.asal_daerah' => ['nullable', 'string', 'max:255'],
            'placement.pendidikan_terakhir' => ['nullable', 'string', 'max:255'],
            'placement.catatan_khusus' => ['nullable', 'string', 'max:5000'],
            'accounts' => ['required', 'array'],
            'accounts.santri' => ['required', 'array'],
            'accounts.santri.username' => ['required', 'alpha_dash', 'max:64', 'unique:users,username'],
            'accounts.santri.email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'accounts.santri.password' => ['required', 'string', 'min:8', 'max:100'],
            'accounts.wali' => ['required', 'array'],
            'accounts.wali.username' => ['required', 'alpha_dash', 'max:64', 'unique:users,username'],
            'accounts.wali.email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'accounts.wali.password' => ['required', 'string', 'min:8', 'max:100'],
        ]);

        $santriRoleId = Role::where('name', Role::SANTRI)->value('id');
        $waliRoleId = Role::where('name', Role::WALI_SANTRI)->value('id');

        abort_unless($santriRoleId && $waliRoleId, 422, 'Role santri/wali_santri belum tersedia.');

        $placement = $validated['placement'];
        $studentGender = (string) ($validated['student']['gender'] ?? '');
        if (! empty($placement['class_id'])) {
            $targetClass = SchoolClass::query()->find((int) $placement['class_id']);
            if (! $targetClass) {
                abort(422, 'Kelas tujuan tidak ditemukan.');
            }
            if (! $targetClass->acceptsStudentGender($studentGender)) {
                abort(422, "Kelas {$targetClass->name} hanya untuk {$targetClass->student_gender_label}.");
            }
        }

        $created = DB::transaction(function () use ($validated, $santriRoleId, $waliRoleId) {
            $studentInput = $validated['student'];
            $placement = $validated['placement'];
            $accounts = $validated['accounts'];

            $santriUsername = (string) $accounts['santri']['username'];
            $waliUsername = (string) $accounts['wali']['username'];

            $santriUser = User::create([
                'name' => $studentInput['full_name'],
                'username' => $santriUsername,
                'email' => $accounts['santri']['email'] ?? "{$santriUsername}@santri.manhood.local",
                'password' => (string) $accounts['santri']['password'],
                'is_active' => true,
                'must_change_password' => true,
                'must_complete_profile' => false,
            ]);
            $santriUser->roles()->sync([$santriRoleId]);

            $student = Student::create([
                'user_id' => $santriUser->id,
                'nis' => $studentInput['nis'],
                'nik' => $studentInput['nik'] ?? null,
                'full_name' => $studentInput['full_name'],
                'birth_place' => $studentInput['birth_place'] ?? null,
                'birth_date' => $studentInput['birth_date'] ?? null,
                'gender' => $studentInput['gender'],
                'address' => $studentInput['address'] ?? null,
                'status' => Student::STATUS_ACTIVE,
                'admission_year' => $studentInput['admission_year'] ?? (int) date('Y'),
                'current_class_id' => $placement['class_id'] ?? null,
            ]);

            $parents = $validated['parents'];
            $addresses = $validated['addresses'];

            $ayahAddress = $this->normalizeAddressInput($addresses['ayah'] ?? []);
            $ibuAddress = $this->normalizeAddressInput(
                ($addresses['ibu_sama_dengan_ayah'] ?? false) ? $addresses['ayah'] : ($addresses['ibu'] ?? [])
            );

            $waliAddressSource = $addresses['wali'] ?? [];
            if ($addresses['wali_sama_dengan_ayah'] ?? false) {
                $waliAddressSource = $addresses['ayah'] ?? [];
            } elseif ($addresses['wali_sama_dengan_ibu'] ?? false) {
                $waliAddressSource = $addresses['ibu'] ?? [];
            }
            $waliAddress = $this->normalizeAddressInput($waliAddressSource);
            $santriAddress = $this->normalizeAddressInput($addresses['santri'] ?? []);

            $waliSource = $parents['wali_data_source'] ?? 'manual';
            $waliInput = match ($waliSource) {
                'ayah' => $parents['ayah'],
                'ibu' => $parents['ibu'],
                default => $parents['wali'] ?? [],
            };

            $waliUser = User::create([
                'name' => $waliInput['full_name'] ?? $parents['ayah']['full_name'],
                'username' => $waliUsername,
                'email' => $accounts['wali']['email'] ?? "{$waliUsername}@wali.manhood.local",
                'password' => (string) $accounts['wali']['password'],
                'is_active' => true,
                'must_change_password' => true,
                'must_complete_profile' => false,
            ]);
            $waliUser->roles()->sync([$waliRoleId]);

            $ayah = Guardian::create($this->guardianPayload(
                $student->id,
                'ayah',
                $parents['ayah'],
                $ayahAddress,
            ));
            $ibu = Guardian::create($this->guardianPayload(
                $student->id,
                'ibu',
                $parents['ibu'],
                $ibuAddress,
            ));
            $wali = Guardian::create($this->guardianPayload(
                $student->id,
                'wali',
                $waliInput,
                $waliAddress,
                userId: $waliUser->id,
            ));

            $student->guardians()->syncWithoutDetaching([
                $ayah->id => ['relationship' => 'ayah'],
                $ibu->id => ['relationship' => 'ibu'],
                $wali->id => ['relationship' => 'wali'],
            ]);

            $emPayload = $this->buildEmProfilePayloadFromCreate(
                $studentInput,
                $placement,
                $ayahAddress,
                $ibuAddress,
                $waliAddress,
                $santriAddress,
            );
            $student->forceFill(['em_profile' => $emPayload])->save();
            $student->emisProfile()->create(EmProfile::fromPayload($emPayload));

            if (! empty($placement['class_id'])) {
                $activePeriodId = AcademicPeriod::query()->active()->value('id');
                if ($activePeriodId) {
                    StudentClassEnrollment::updateOrCreate(
                        [
                            'student_id' => $student->id,
                            'period_id' => $activePeriodId,
                        ],
                        [
                            'class_id' => $placement['class_id'],
                        ]
                    );
                }
            }

            if (! empty($placement['dorm_room_id'])) {
                DormAssignment::where('student_id', $student->id)
                    ->whereNull('checkout_date')
                    ->update([
                        'checkout_date' => $placement['checkin_date'] ?? now()->toDateString(),
                    ]);

                DormAssignment::create([
                    'student_id' => $student->id,
                    'room_id' => $placement['dorm_room_id'],
                    'checkin_date' => $placement['checkin_date'] ?? now()->toDateString(),
                ]);
            }

            $student->load([
                'currentClass:id,name,grade_level_id',
                'guardians' => fn ($q) => $q->withPivot('relationship'),
                'currentDormAssignment.room.building',
                'emisProfile',
            ]);

            return [
                'student' => $student,
                'credentials' => [
                    'santri' => [
                        'username' => $santriUsername,
                        'password' => (string) $accounts['santri']['password'],
                    ],
                    'wali' => [
                        'username' => $waliUsername,
                        'password' => (string) $accounts['wali']['password'],
                    ],
                ],
            ];
        });

        return response()->json([
            'message' => 'Data santri baru berhasil disimpan.',
            'student' => $this->studentPayload($created['student']),
            'credentials' => $created['credentials'],
            'dorm_label' => $this->dormLabel($created['student']),
        ], 201);
    }

    public function studentDetail(Request $request, Student $student): JsonResponse
    {
        $student->load([
            'currentClass:id,name,grade_level_id',
            'violationSummary',
            'currentDormAssignment.room.building',
            'guardians' => fn ($q) => $q->withPivot('relationship'),
            'emisProfile',
        ]);

        $activeSemester = AcademicPeriod::query()->active()->with('semester:id,name')->first()?->semester;
        $semesterId = $request->semester_id ?? $activeSemester?->id;

        $grades = [];
        $semester = null;
        if ($semesterId) {
            $semester = Semester::with('academicYear:id,name')->find($semesterId);
            $grades = Score::where('student_id', $student->id)
                ->whereHas('period', fn ($q) => $q->where('semester_id', $semesterId))
                ->with(['subject:id,name', 'component:id,name'])
                ->get();
        }

        $recentViolations = StudentViolation::where('student_id', $student->id)
            ->with('violationType:id,name,points,category')
            ->orderByDesc('date')
            ->limit(10)->get();

        $semesters = Semester::with('academicYear:id,name')->orderByDesc('id')->get(['id', 'name', 'academic_year_id']);

        return response()->json([
            'student' => $this->studentPayload($student),
            'dorm_label' => $this->dormLabel($student),
            'semesters' => $semesters,
            'currentSemesterId' => $semesterId,
            'semester' => $semester,
            'grades' => $grades,
            'recentViolations' => $recentViolations,
        ]);
    }

    /**
     * Update EMIS / profil santri (admin mengedit santri manapun).
     * Validasi sama seperti SantriController::updateProfile.
     */
    public function updateStudent(Request $request, Student $student): JsonResponse
    {
        $validated = $request->validate([
            'em_profile' => 'nullable|array',
            'full_name' => 'sometimes|nullable|string|max:255',
            'nik' => 'sometimes|nullable|string|max:32',
            'nis' => 'sometimes|nullable|string|max:32',
            'birth_place' => 'sometimes|nullable|string|max:120',
            'birth_date' => 'sometimes|nullable|date',
            'gender' => 'sometimes|nullable|in:'.implode(',', [Student::GENDER_MALE, Student::GENDER_FEMALE]),
            'address' => 'sometimes|nullable|string|max:2000',
        ]);

        $incoming = is_array($validated['em_profile'] ?? null) ? $validated['em_profile'] : [];
        if ($incoming !== []) {
            $this->upsertEmProfile($student, $incoming);
        }

        if (array_key_exists('full_name', $validated)) {
            $student->full_name = $validated['full_name'] ?? $student->full_name;
        }
        if (array_key_exists('nik', $validated)) {
            $student->nik = $validated['nik'];
        }
        if (array_key_exists('nis', $validated)) {
            $student->nis = $validated['nis'];
        }
        if (array_key_exists('birth_place', $validated)) {
            $student->birth_place = $validated['birth_place'];
        }
        if (array_key_exists('birth_date', $validated)) {
            $student->birth_date = $validated['birth_date'];
        }
        if (array_key_exists('gender', $validated)) {
            $student->gender = $validated['gender'];
        }
        if (array_key_exists('address', $validated)) {
            $student->address = $validated['address'];
        }
        $student->save();

        $student->load([
            'currentClass:id,name,grade_level_id',
            'violationSummary',
            'currentDormAssignment.room.building',
            'guardians' => fn ($q) => $q->withPivot('relationship'),
            'emisProfile',
        ]);

        return response()->json([
            'student' => $this->studentPayload($student),
            'dorm_label' => $this->dormLabel($student),
            'message' => 'Data santri berhasil disimpan.',
        ]);
    }

    private function upsertEmProfile(Student $student, array $incoming): void
    {
        $student->loadMissing('emisProfile');
        $current = $student->emProfilePayload();
        $merged = array_replace_recursive($current, $incoming);
        $attributes = EmProfile::fromPayload($merged);
        $student->emisProfile()->updateOrCreate([], $attributes);
        $student->forceFill(['em_profile' => $merged])->save();
        $student->unsetRelation('emisProfile');
        $student->load('emisProfile');
    }

    private function studentPayload(Student $student): array
    {
        $payload = $student->toArray();
        $payload['em_profile'] = $student->emProfilePayload() ?: [
            'santri' => [],
            'alamat' => [],
        ];

        return $payload;
    }

    private function normalizeAddressInput(array $source): array
    {
        return [
            'tinggal_luar_negeri' => $source['tinggal_luar_negeri'] ?? false,
            'status_kepemilikan_rumah' => $source['status_kepemilikan_rumah'] ?? null,
            'sama_dengan_ktp' => $source['sama_dengan_ktp'] ?? false,
            'provinsi' => $source['provinsi'] ?? null,
            'kabupaten' => $source['kabupaten'] ?? null,
            'kecamatan' => $source['kecamatan'] ?? null,
            'kelurahan' => $source['kelurahan'] ?? null,
            'dusun' => $source['dusun'] ?? ($source['kampung'] ?? null),
            'rw' => $source['rw'] ?? null,
            'rt' => $source['rt'] ?? null,
            'alamat' => $source['alamat'] ?? null,
            'kode_pos' => $source['kode_pos'] ?? null,
            'nik_ktp' => $source['nik_ktp'] ?? null,
            'domisili' => $source['domisili'] ?? null,
            'jarak_tempat_tinggal_lembaga' => $source['jarak_tempat_tinggal_lembaga'] ?? null,
            'transportasi_ke_lembaga' => $source['transportasi_ke_lembaga'] ?? null,
            'koordinat' => $source['koordinat'] ?? null,
        ];
    }

    private function buildEmProfilePayloadFromCreate(
        array $studentInput,
        array $placement,
        array $ayahAddress,
        array $ibuAddress,
        array $waliAddress,
        array $santriAddress,
    ): array {
        return [
            'santri' => [
                'nisn' => $studentInput['nisn'] ?? null,
                'nism' => $studentInput['nism'] ?? null,
                'kewarganegaraan' => $studentInput['kewarganegaraan'] ?? null,
                'agama' => $studentInput['agama'] ?? null,
                'no_hp' => $studentInput['no_hp'] ?? null,
                'email' => $studentInput['email'] ?? null,
                'status_mukim' => $studentInput['status_mukim'] ?? null,
                'status_tempat_tinggal' => $studentInput['status_tempat_tinggal'] ?? null,
                'asal_daerah' => $placement['asal_daerah'] ?? null,
                'pendidikan_sebelumnya' => $placement['pendidikan_terakhir'] ?? null,
                'catatan_khusus' => $placement['catatan_khusus'] ?? null,
                'jarak_tempat_tinggal_lembaga' => $santriAddress['jarak_tempat_tinggal_lembaga'] ?? null,
                'transportasi_ke_lembaga' => $santriAddress['transportasi_ke_lembaga'] ?? null,
                'koordinat' => $santriAddress['koordinat'] ?? null,
            ],
            'alamat' => [
                'ayah' => $ayahAddress,
                'ibu' => $ibuAddress,
                'wali' => $waliAddress,
                'santri' => $santriAddress,
            ],
        ];
    }

    private function guardianPayload(
        int $studentId,
        string $relationship,
        array $data,
        array $address,
        ?int $userId = null,
    ): array {
        return [
            'student_id' => $studentId,
            'user_id' => $userId,
            'relationship' => $relationship,
            'status' => $data['status'] ?? null,
            'full_name' => $data['full_name'] ?? null,
            'nik' => $data['nik'] ?? null,
            'kewarganegaraan' => $data['kewarganegaraan'] ?? null,
            'birth_place' => $data['birth_place'] ?? null,
            'birth_date' => $data['birth_date'] ?? null,
            'phone' => $data['phone'] ?? null,
            'without_phone' => (bool) ($data['without_phone'] ?? false),
            'email' => $data['email'] ?? null,
            'last_education' => $data['last_education'] ?? null,
            'occupation' => $data['occupation'] ?? null,
            'income_band' => $data['income_band'] ?? null,
            'monthly_income' => $data['monthly_income'] ?? null,
            'no_kks' => $data['no_kks'] ?? null,
            'no_pkh' => $data['no_pkh'] ?? null,
            'tinggal_luar_negeri' => (bool) ($address['tinggal_luar_negeri'] ?? false),
            'status_kepemilikan_rumah' => $address['status_kepemilikan_rumah'] ?? null,
            'domisili' => $address['domisili'] ?? null,
            'provinsi' => $address['provinsi'] ?? null,
            'kabupaten' => $address['kabupaten'] ?? null,
            'kecamatan' => $address['kecamatan'] ?? null,
            'kelurahan' => $address['kelurahan'] ?? null,
            'dusun' => $address['dusun'] ?? null,
            'rw' => $address['rw'] ?? null,
            'rt' => $address['rt'] ?? null,
            'alamat' => $address['alamat'] ?? null,
            'kode_pos' => $address['kode_pos'] ?? null,
            'nik_ktp' => $address['nik_ktp'] ?? null,
        ];
    }

    private function dormLabel(Student $student): ?string
    {
        $a = $student->currentDormAssignment;
        if (! $a || ! $a->room) {
            return null;
        }
        $room = $a->room;
        $building = $room->building;
        $roomNo = $room->room_number ?? '';
        $name = $building?->name ?? '';

        if ($roomNo === '' && $name === '') {
            return null;
        }

        return 'Kobong '.trim($roomNo.' '.$name);
    }

    public function kitabGrades(Request $request): JsonResponse
    {
        $classesQuery = SchoolClass::query()->orderBy('order')->orderBy('name');
        $subjectsQuery = Subject::query()->orderBy('name');

        $grades = [];
        $students = [];

        $subjectId = $request->input('subject_id', $request->kitab_subject_id);
        $periodId = $request->input('period_id');
        if (! $periodId && $request->semester_id) {
            $periodId = AcademicPeriod::where('semester_id', $request->semester_id)->value('id');
        }
        $componentId = $request->input('component_id') ?? AssessmentComponent::query()->orderByDesc('is_core_required')->orderBy('id')->value('id');

        if ($request->class_id && $subjectId && $periodId && $componentId) {
            $students = Student::where('current_class_id', $request->class_id)
                ->where('status', Student::STATUS_ACTIVE)
                ->orderBy('full_name')
                ->get(['id', 'nis', 'full_name']);

            $grades = Score::where('subject_id', $subjectId)
                ->where('period_id', $periodId)
                ->where('component_id', $componentId)
                ->whereIn('student_id', $students->pluck('id'))
                ->get()
                ->keyBy('student_id');
        }

        return response()->json([
            'classes' => $classesQuery->get(['id', 'name', 'grade_level_id']),
            'subjects' => $subjectsQuery->get(['id', 'name']),
            'semesters' => Semester::with('academicYear:id,name')->orderByDesc('id')->get(['id', 'name', 'academic_year_id']),
            'academicPeriods' => AcademicPeriod::query()->orderByDesc('id')->get(['id', 'academic_year_id', 'semester_id', 'is_active']),
            'assessmentComponents' => AssessmentComponent::orderByDesc('is_core_required')->orderBy('type')->orderBy('name')->get(['id', 'name', 'type', 'is_core_required']),
            'students' => $students,
            'grades' => $grades,
            'filters' => $request->only(['class_id', 'kitab_subject_id', 'subject_id', 'semester_id', 'period_id', 'component_id']),
            'activeSemester' => AcademicPeriod::query()->active()->with('semester:id,name')->first()?->semester?->only(['id', 'name']),
        ]);
    }

    public function leavePermissions(Request $request): JsonResponse
    {
        $query = LeavePermission::with(['student:id,nis,full_name', 'approver:id,name'])
            ->when($request->status && $request->status !== 'all', fn ($q) => $q->where('status', $request->status))
            ->when($request->search, fn ($q, $s) => $q->whereHas('student', fn ($sq) => $sq->where('full_name', 'ilike', "%{$s}%")))
            ->orderByDesc('created_at');

        $permissions = $query->paginate(15)->withQueryString();

        return response()->json([
            'permissions' => $permissions,
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    public function storeLeavePermission(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'reason' => ['required', 'string'],
            'leave_date' => ['required', 'date'],
            'return_date' => ['nullable', 'date', 'after_or_equal:leave_date'],
        ]);

        $leave = LeavePermission::create($validated);

        return response()->json([
            'message' => 'Permohonan izin berhasil diajukan.',
            'leave' => $leave->load('student'),
        ], 201);
    }

    public function approveLeave(Request $request, LeavePermission $leavePermission): JsonResponse
    {
        $leavePermission->update([
            'status' => LeavePermission::STATUS_APPROVED,
            'approved_by' => $request->user()->id,
        ]);

        $leavePermission->loadMissing('student.user', 'student.guardians.user');
        $student = $leavePermission->student;
        if ($student?->user) {
            $student->user->notify(new LeaveStatusChangedNotification($leavePermission));
        }
        if ($student) {
            $student->guardians
                ->pluck('user')
                ->filter()
                ->unique('id')
                ->each(fn ($user) => $user->notify(new LeaveStatusChangedNotification($leavePermission)));
        }

        return response()->json(['message' => 'Izin disetujui.', 'leave' => $leavePermission->fresh()]);
    }

    public function rejectLeave(Request $request, LeavePermission $leavePermission): JsonResponse
    {
        $request->validate(['rejection_reason' => 'required|string']);

        $leavePermission->update([
            'status' => LeavePermission::STATUS_REJECTED,
            'approved_by' => $request->user()->id,
            'rejection_reason' => $request->rejection_reason,
        ]);

        $leavePermission->loadMissing('student.user', 'student.guardians.user');
        $student = $leavePermission->student;
        if ($student?->user) {
            $student->user->notify(new LeaveStatusChangedNotification($leavePermission));
        }
        if ($student) {
            $student->guardians
                ->pluck('user')
                ->filter()
                ->unique('id')
                ->each(fn ($user) => $user->notify(new LeaveStatusChangedNotification($leavePermission)));
        }

        return response()->json(['message' => 'Izin ditolak.', 'leave' => $leavePermission->fresh()]);
    }

    public function markReturned(LeavePermission $leavePermission): JsonResponse
    {
        $leavePermission->update(['actual_return_date' => now()->toDateString()]);

        return response()->json(['message' => 'Santri sudah kembali.', 'leave' => $leavePermission->fresh()]);
    }

    public function violations(Request $request): JsonResponse
    {
        $query = Student::where('status', Student::STATUS_ACTIVE)
            ->with(['violationSummary', 'currentClass:id,name'])
            ->when($request->class_id, fn ($q, $id) => $q->where('current_class_id', $id))
            ->when($request->search, fn ($q, $s) => $q->where('full_name', 'ilike', "%{$s}%"))
            ->orderBy('full_name');

        $students = $query->paginate(15)->withQueryString();

        $recentViolations = StudentViolation::with(['student:id,full_name,nis', 'violationType:id,name,points'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return response()->json([
            'students' => $students,
            'recentViolations' => $recentViolations,
            'classes' => SchoolClass::orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['class_id', 'search']),
        ]);
    }

    public function storeViolation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'violation_type_id' => ['required', 'exists:violation_types,id'],
            'date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
        ]);

        $validated['handled_by'] = $request->user()->id;
        $validated['status'] = StudentViolation::STATUS_OPEN;

        $violation = StudentViolation::create($validated);
        $this->recalculateViolationSummary($validated['student_id']);

        $violation->loadMissing('student.user', 'student.guardians.user', 'violationType');
        $student = $violation->student;
        if ($student?->user) {
            $student->user->notify(new ViolationRecordedNotification($violation));
        }
        if ($student) {
            $student->guardians
                ->pluck('user')
                ->filter()
                ->unique('id')
                ->each(fn ($user) => $user->notify(new ViolationRecordedNotification($violation)));
        }

        return response()->json([
            'message' => 'Pelanggaran berhasil dicatat.',
            'violation' => $violation->load(['student', 'violationType']),
        ], 201);
    }

    public function resolveViolation(Request $request, StudentViolation $violation): JsonResponse
    {
        $request->validate(['resolution_notes' => 'nullable|string']);

        $violation->update([
            'status' => StudentViolation::STATUS_RESOLVED,
            'resolution_notes' => $request->resolution_notes,
        ]);

        return response()->json(['message' => 'Pelanggaran berhasil diselesaikan.', 'violation' => $violation->fresh()]);
    }

    public function violationTypes(Request $request): JsonResponse
    {
        $types = ViolationType::orderBy('category')->orderBy('name')->get();

        return response()->json(['types' => $types]);
    }

    private function recalculateViolationSummary(int $studentId): void
    {
        $totalPoints = StudentViolation::where('student_id', $studentId)
            ->join('violation_types', 'student_violations.violation_type_id', '=', 'violation_types.id')
            ->sum('violation_types.points');

        $lastDate = StudentViolation::where('student_id', $studentId)->max('date');

        ViolationSummary::updateOrCreate(
            ['student_id' => $studentId],
            ['total_points' => $totalPoints, 'last_violation_date' => $lastDate]
        );
    }
}
