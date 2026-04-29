<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\Diniyyah\AcademicSchedule;
use App\Models\Diniyyah\AssessmentComponent;
use App\Models\Diniyyah\SchoolClass;
use App\Models\Diniyyah\Score;
use App\Models\Diniyyah\Subject;
use App\Models\Diniyyah\TeacherAssignment;
use App\Models\ReportCard;
use App\Models\ReportCardTemplate;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentViolation;
use App\Notifications\GradeUpdatedNotification;
use App\Notifications\ReportCardPublishedNotification;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GuruController extends Controller
{
    private function getMyClassIds(Request $request): array
    {
        return $request->user()->homeroomAssignments()->pluck('class_id')->unique()->values()->all();
    }

    private function ensureStudentInMyClass(Request $request, int $studentId): void
    {
        $student = Student::findOrFail($studentId);
        $classIds = $this->getMyClassIds($request);
        if (! in_array($student->current_class_id, $classIds, true)) {
            abort(403, 'Santri ini tidak berada di kelas Anda.');
        }
    }

    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();

        $assignments = TeacherAssignment::where('teacher_id', $user->id)
            ->with(['schoolClass:id,name,grade_level_id', 'subject:id,name'])
            ->get();

        $waliKelasClasses = [];
        $waliClassIds = $user->homeroomAssignments()->pluck('class_id')->unique();
        if ($waliClassIds->isNotEmpty()) {
            $waliKelasClasses = SchoolClass::whereIn('id', $waliClassIds)
                ->withCount(['students' => fn ($q) => $q->where('status', Student::STATUS_ACTIVE)])
                ->orderBy('order')
                ->orderBy('name')
                ->get(['id', 'name', 'grade_level_id']);
        }

        return response()->json([
            'assignments' => $assignments,
            'waliKelasClasses' => $waliKelasClasses,
        ]);
    }

    public function teachingAssignments(Request $request): JsonResponse
    {
        $user = $request->user();
        $hasLimitedView = ! $user->isAdmin();

        $classesQuery = SchoolClass::query()->orderBy('order')->orderBy('name');
        $subjectsQuery = Subject::query()->orderBy('name');

        $classSubjectMap = [];
        if ($hasLimitedView) {
            $assignmentClassIds = TeacherAssignment::where('teacher_id', $user->id)->pluck('class_id')->unique();
            $assignmentSubjectIds = TeacherAssignment::where('teacher_id', $user->id)->pluck('subject_id')->unique();
            $classesQuery->whereIn('id', $assignmentClassIds);
            $subjectsQuery->whereIn('id', $assignmentSubjectIds);

            $classSubjectMap = TeacherAssignment::where('teacher_id', $user->id)
                ->get(['class_id', 'subject_id'])
                ->groupBy('class_id')
                ->map(fn ($items) => $items->pluck('subject_id')->values()->all())
                ->all();
        }

        return response()->json([
            'classes' => $classesQuery->get(['id', 'name', 'grade_level_id']),
            'subjects' => $subjectsQuery->get(['id', 'name']),
            'classSubjectMap' => $classSubjectMap,
            'semesters' => Semester::with('academicYear:id,name')->orderByDesc('id')->get(['id', 'name', 'academic_year_id']),
            'academicPeriods' => AcademicPeriod::query()->orderByDesc('id')->get(['id', 'academic_year_id', 'semester_id', 'is_active']),
        ]);
    }

    private function dayName(int $day): string
    {
        return match ($day) {
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Ahad',
            default => 'Unknown',
        };
    }

    public function schedule(Request $request): JsonResponse
    {
        $user = $request->user();
        $activeSemester = AcademicPeriod::query()->active()->with('semester:id,name')->first()?->semester;

        $schedulesQuery = AcademicSchedule::query()
            ->where('teacher_id', $user->id)
            ->with([
                'schoolClass:id,name,grade_level_id',
                'subject:id,name',
            ])
            ->orderBy('day')
            ->orderBy('time_start');

        $schedules = $schedulesQuery->get();

        $week = [];
        foreach ($schedules as $item) {
            $day = (int) $item->day;
            if (! isset($week[$day])) {
                $week[$day] = [
                    'day_of_week' => $day,
                    'day_name' => $this->dayName($day),
                    'entries' => [],
                ];
            }
            $week[$day]['entries'][] = [
                'id' => $item->id,
                'class' => [
                    'id' => $item->schoolClass->id,
                    'name' => $item->schoolClass->name,
                    'grade_level_id' => $item->schoolClass->grade_level_id,
                ],
                'subject' => [
                    'id' => $item->subject->id,
                    'name' => $item->subject->name,
                ],
                'start_time' => $item->time_start,
                'end_time' => $item->time_end,
                'room' => null,
            ];
        }

        ksort($week);

        return response()->json([
            'teacher' => $user->only(['id', 'name']),
            'semester' => $activeSemester ? $activeSemester->only(['id', 'name']) : null,
            'week' => array_values($week),
        ]);
    }

    public function kitabGradesIndex(Request $request): JsonResponse
    {
        $user = $request->user();
        $hasLimitedView = ! $user->isAdmin();

        $classesQuery = SchoolClass::query()->orderBy('order')->orderBy('name');
        $subjectsQuery = Subject::query()->orderBy('name');

        if ($hasLimitedView) {
            $assignmentClassIds = TeacherAssignment::where('teacher_id', $user->id)->pluck('class_id')->unique();
            $assignmentSubjectIds = TeacherAssignment::where('teacher_id', $user->id)->pluck('subject_id')->unique();
            $classesQuery->whereIn('id', $assignmentClassIds);
            $subjectsQuery->whereIn('id', $assignmentSubjectIds);
        }

        $grades = [];
        $students = [];

        $subjectId = $request->input('subject_id', $request->input('kitab_subject_id'));
        $periodId = $request->input('period_id');
        if (! $periodId && $request->semester_id) {
            $periodId = AcademicPeriod::where('semester_id', $request->semester_id)->value('id');
        }
        $componentId = $request->input('component_id') ?? AssessmentComponent::query()->orderBy('id')->value('id');

        if ($request->class_id && $subjectId && $periodId && $componentId) {
            if ($hasLimitedView) {
                $allowed = TeacherAssignment::where('teacher_id', $user->id)
                    ->where('class_id', $request->class_id)
                    ->where('subject_id', $subjectId)
                    ->where('period_id', $periodId)
                    ->exists();
                if (! $allowed) {
                    abort(403, 'Anda tidak ditugaskan untuk mengajar mata pelajaran ini di kelas ini.');
                }
            }

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

        $classSubjectMap = [];
        if ($hasLimitedView) {
            $classSubjectMap = TeacherAssignment::where('teacher_id', $user->id)
                ->get(['class_id', 'subject_id'])
                ->groupBy('class_id')
                ->map(fn ($items) => $items->pluck('subject_id')->values()->all())
                ->all();
        }

        return response()->json([
            'classes' => $classesQuery->get(['id', 'name', 'grade_level_id']),
            'subjects' => $subjectsQuery->get(['id', 'name']),
            'semesters' => Semester::with('academicYear:id,name')->orderByDesc('id')->get(['id', 'name', 'academic_year_id']),
            'academicPeriods' => AcademicPeriod::query()->orderByDesc('id')->get(['id', 'academic_year_id', 'semester_id', 'is_active']),
            'assessmentComponents' => AssessmentComponent::orderBy('type')->orderBy('name')->get(['id', 'name', 'type']),
            'students' => $students,
            'grades' => $grades,
            'filters' => $request->only(['class_id', 'kitab_subject_id', 'subject_id', 'semester_id', 'period_id', 'component_id']),
            'isGuru' => $hasLimitedView,
            'classSubjectMap' => $classSubjectMap,
            'activeSemester' => AcademicPeriod::query()->active()->with('semester:id,name')->first()?->semester?->only(['id', 'name']),
        ]);
    }

    public function kitabGradesStore(Request $request): JsonResponse
    {
        $subjectId = $request->input('subject_id', $request->input('kitab_subject_id'));
        $periodId = $request->input('period_id');
        if (! $periodId && $request->semester_id) {
            $periodId = AcademicPeriod::where('semester_id', $request->semester_id)->value('id');
        }
        $request->merge([
            'subject_id' => $subjectId,
            'period_id' => $periodId,
        ]);

        $rules = [
            'grades' => ['required', 'array'],
            'grades.*.student_id' => ['required', 'exists:students,id'],
            'grades.*.score' => ['required', 'numeric', 'min:0', 'max:100'],
            'grades.*.notes' => ['nullable', 'string'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'period_id' => ['required', 'exists:academic_periods,id'],
            'component_id' => ['required', 'exists:assessment_components,id'],
            'class_id' => ['required', 'exists:classes,id'],
        ];

        $request->validate($rules);

        $user = $request->user();
        if (! $user->isAdmin()) {
            $allowed = TeacherAssignment::where('teacher_id', $user->id)
                ->where('class_id', $request->class_id)
                ->where('subject_id', $request->subject_id)
                ->where('period_id', $request->period_id)
                ->exists();
            if (! $allowed) {
                throw ValidationException::withMessages([
                    'subject_id' => ['Anda tidak ditugaskan untuk mengajar mata pelajaran ini di kelas ini.'],
                ]);
            }
        }

        foreach ($request->grades as $gradeData) {
            Score::updateOrCreate(
                [
                    'student_id' => $gradeData['student_id'],
                    'subject_id' => $request->subject_id,
                    'component_id' => $request->component_id,
                    'period_id' => $request->period_id,
                ],
                [
                    'teacher_id' => $user->id,
                    'score' => $gradeData['score'],
                    'status' => Score::STATUS_SUBMITTED,
                ]
            );
        }

        $studentIds = collect($request->grades)
            ->pluck('student_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($studentIds->isNotEmpty()) {
            $students = Student::query()
                ->whereIn('id', $studentIds->all())
                ->with(['user', 'guardians.user'])
                ->get();

            foreach ($students as $student) {
                $sampleScore = Score::query()
                    ->where('student_id', $student->id)
                    ->where('subject_id', (int) $request->subject_id)
                    ->where('period_id', (int) $request->period_id)
                    ->where('component_id', (int) $request->component_id)
                    ->with('subject:id,name')
                    ->latest('id')
                    ->first();

                if (! $sampleScore) {
                    continue;
                }

                if ($student->user) {
                    $student->user->notify(new GradeUpdatedNotification($sampleScore));
                }

                $student->guardians
                    ->pluck('user')
                    ->filter()
                    ->unique('id')
                    ->each(fn ($user) => $user->notify(new GradeUpdatedNotification($sampleScore)));
            }
        }

        return response()->json(['message' => 'Nilai berhasil disimpan.'], 200);
    }

    public function reportCardsIndex(Request $request): JsonResponse
    {
        $classIds = $this->getMyClassIds($request);
        if (empty($classIds)) {
            return response()->json([
                'classes' => [],
                'semesters' => [],
                'students' => [],
                'filters' => [],
            ]);
        }

        $students = [];
        if ($request->class_id && $request->semester_id && in_array((int) $request->class_id, $classIds, true)) {
            $students = Student::where('current_class_id', $request->class_id)
                ->where('status', Student::STATUS_ACTIVE)
                ->orderBy('full_name')
                ->get(['id', 'nis', 'full_name'])
                ->map(function ($student) use ($request) {
                    $rc = ReportCard::where('student_id', $student->id)
                        ->where('semester_id', $request->semester_id)
                        ->first();
                    $student->report_card = $rc;

                    return $student;
                });
        }

        return response()->json([
            'classes' => SchoolClass::query()->whereIn('id', $classIds)->orderBy('order')->orderBy('name')->get(['id', 'name', 'grade_level_id']),
            'semesters' => Semester::with('academicYear:id,name')->orderByDesc('id')->get(['id', 'name', 'academic_year_id']),
            'students' => $students,
            'filters' => $request->only(['class_id', 'semester_id']),
        ]);
    }

    public function reportCardsPreview(Request $request): JsonResponse
    {
        $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'semester_id' => ['required', 'exists:semesters,id'],
        ]);

        $this->ensureStudentInMyClass($request, (int) $request->student_id);

        $data = $this->buildReportData((int) $request->student_id, (int) $request->semester_id);

        return response()->json($data);
    }

    public function reportCardsSaveNotes(Request $request): JsonResponse
    {
        $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'semester_id' => ['required', 'exists:semesters,id'],
            'wali_kelas_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->ensureStudentInMyClass($request, (int) $request->student_id);

        $reportCard = ReportCard::firstOrNew([
            'student_id' => $request->student_id,
            'semester_id' => $request->semester_id,
        ]);

        if (! $reportCard->verification_token) {
            $reportCard->verification_token = Str::random(40);
        }

        $reportCard->wali_kelas_notes = $request->wali_kelas_notes;
        $reportCard->generated_by = $request->user()->id;
        $reportCard->generated_at = now();
        $reportCard->save();

        $reportCard->loadMissing('student.guardians.user', 'student.user');
        $student = $reportCard->student;
        if ($student?->user) {
            $student->user->notify(new ReportCardPublishedNotification($reportCard));
        }
        if ($student) {
            $student->guardians
                ->pluck('user')
                ->filter()
                ->unique('id')
                ->each(fn ($user) => $user->notify(new ReportCardPublishedNotification($reportCard)));
        }

        return response()->json(['message' => 'Catatan raport berhasil disimpan.'], 200);
    }

    public function reportCardsPdf(Request $request): Response
    {
        $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'semester_id' => ['required', 'exists:semesters,id'],
        ]);

        $this->ensureStudentInMyClass($request, (int) $request->student_id);

        $data = $this->buildReportDataForPdf((int) $request->student_id, (int) $request->semester_id);

        $template = ReportCardTemplate::getActive();
        $view = ($template && $template->isCanva()) ? 'pdf.report-card-canva' : 'pdf.report-card';
        $pdf = Pdf::loadView($view, $data);

        $student = Student::find($request->student_id);
        $filename = "raport_{$student->nis}_{$request->semester_id}.pdf";

        return $pdf->download($filename);
    }

    private function buildReportDataForPdf(int $studentId, int $semesterId): array
    {
        $student = Student::with('currentClass')->findOrFail($studentId);
        $semester = Semester::with('academicYear')->findOrFail($semesterId);

        $grades = Score::where('student_id', $studentId)
            ->whereHas('period', fn ($q) => $q->where('semester_id', $semesterId))
            ->with(['subject:id,name', 'component:id,name'])
            ->get();

        $violations = StudentViolation::where('student_id', $studentId)
            ->whereBetween('date', [$semester->start_date, $semester->end_date])
            ->with('violationType:id,name,points,category')
            ->get();

        $reportCard = ReportCard::firstOrCreate(
            ['student_id' => $studentId, 'semester_id' => $semesterId],
            ['verification_token' => Str::random(40)]
        );

        if (! $reportCard->verification_token) {
            $reportCard->update(['verification_token' => Str::random(40)]);
            $reportCard->refresh();
        }

        $avgScore = $grades->count() > 0 ? round((float) $grades->avg('score'), 1) : null;

        $template = ReportCardTemplate::getActive();
        $templateConfig = $template?->config ?? ReportCardTemplate::defaultConfig();

        $verificationUrl = url('raport/verify/'.$reportCard->verification_token);
        $qrCodeBase64 = $this->generateQrCodeBase64($verificationUrl);

        return [
            'student' => $student,
            'semester' => $semester,
            'grades' => $grades,
            'violations' => $violations,
            'reportCard' => $reportCard,
            'avgScore' => $avgScore,
            'templateConfig' => $templateConfig,
            'verificationUrl' => $verificationUrl,
            'qrCodeBase64' => $qrCodeBase64,
        ];
    }

    private function buildReportData(int $studentId, int $semesterId): array
    {
        $data = $this->buildReportDataForPdf($studentId, $semesterId);
        unset($data['qrCodeBase64']);

        return $data;
    }

    private function generateQrCodeBase64(string $url): string
    {
        $writer = new PngWriter;
        $qrCode = new QrCode(
            data: $url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Low,
            size: 80,
            margin: 2
        );
        $result = $writer->write($qrCode);

        return base64_encode($result->getString());
    }
}
