<?php

namespace App\Http\Controllers\WaliKelas;

use App\Http\Controllers\Controller;
use App\Models\Diniyyah\ClassWali;
use App\Models\Diniyyah\SchoolClass;
use App\Models\Diniyyah\Score;
use App\Models\ReportCard;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentViolation;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Contracts\View\View as ViewResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class WaliKelasReportController extends Controller
{
    private function getMyClassIds(Request $request): array
    {
        return $request->user()->homeroomAssignments()->pluck('class_id')->unique()->values()->all();
    }

    private function ensureStudentInMyClass(Request $request, int $studentId): void
    {
        $student = Student::findOrFail($studentId);
        $classIds = $this->getMyClassIds($request);
        if (! in_array($student->current_class_id, $classIds)) {
            abort(403, 'Santri ini tidak berada di kelas Anda.');
        }
    }

    public function index(Request $request): Response
    {
        $classIds = $this->getMyClassIds($request);
        if (empty($classIds)) {
            return Inertia::render('wali-kelas/report-cards/index', [
                'classes' => [],
                'semesters' => [],
                'students' => [],
                'filters' => [],
            ]);
        }

        $students = [];
        if ($request->class_id && $request->semester_id && in_array((int) $request->class_id, $classIds)) {
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

        return Inertia::render('wali-kelas/report-cards/index', [
            'classes' => SchoolClass::query()->whereIn('id', $classIds)->orderBy('order')->orderBy('name')->get(['id', 'name', 'grade_level_id']),
            'semesters' => Semester::with('academicYear:id,name')->orderByDesc('id')->get(['id', 'name', 'academic_year_id']),
            'students' => $students,
            'filters' => $request->only(['class_id', 'semester_id']),
        ]);
    }

    public function preview(Request $request): Response
    {
        $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'semester_id' => ['required', 'exists:semesters,id'],
        ]);

        $this->ensureStudentInMyClass($request, (int) $request->student_id);

        $data = $this->buildReportData($request->student_id, $request->semester_id);
        $data['saveUrl'] = '/wali-kelas/report-cards/save-notes';
        $data['backUrl'] = '/wali-kelas/report-cards?class_id='.$request->query('class_id', '').'&semester_id='.$request->semester_id;
        $data['pdfUrl'] = '/wali-kelas/report-cards/pdf?student_id='.$request->student_id.'&semester_id='.$request->semester_id;
        $data['previewBladeUrl'] = '/wali-kelas/report-cards/preview-blade?student_id='.$request->student_id.'&semester_id='.$request->semester_id;
        $data['breadcrumbs'] = [
            ['title' => 'Dashboard', 'href' => '/dashboard'],
            ['title' => 'Raport Kelas', 'href' => '/wali-kelas/report-cards'],
            ['title' => 'Preview', 'href' => '#'],
        ];

        return Inertia::render('wali-kelas/report-cards/preview', $data);
    }

    public function saveNotes(Request $request): RedirectResponse
    {
        $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'semester_id' => ['required', 'exists:semesters,id'],
            'wali_kelas_notes' => ['nullable', 'string', 'max:1000'],
            'wali_kelas_signature' => ['nullable', 'image', 'max:2048'],
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

        if ($request->hasFile('wali_kelas_signature')) {
            $path = $request->file('wali_kelas_signature')->store('report-card-signatures/wali-kelas', 'public');
            $request->user()->update(['homeroom_signature_path' => $path]);
        }

        $classId = $request->query('class_id') ?? $request->input('class_id');
        $semesterId = $request->query('semester_id') ?? $request->input('semester_id');
        $backUrl = ($classId && $semesterId)
            ? '/wali-kelas/report-cards?class_id='.$classId.'&semester_id='.$semesterId
            : '/wali-kelas/report-cards';

        return redirect($backUrl)->with('success', 'Catatan raport berhasil disimpan.');
    }

    public function downloadPdf(Request $request)
    {
        $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'semester_id' => ['required', 'exists:semesters,id'],
        ]);

        $this->ensureStudentInMyClass($request, (int) $request->student_id);

        $data = $this->buildReportDataForPdf($request->student_id, $request->semester_id);

        $pdf = Pdf::loadView('pdf.report-card', $data);

        $student = Student::find($request->student_id);
        $filename = "raport_{$student->nis}_{$request->semester_id}.pdf";

        return $pdf->download($filename);
    }

    public function previewBlade(Request $request): ViewResponse
    {
        $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'semester_id' => ['required', 'exists:semesters,id'],
        ]);

        $this->ensureStudentInMyClass($request, (int) $request->student_id);

        $data = $this->buildReportDataForPdf((int) $request->student_id, (int) $request->semester_id);

        return view('pdf.report-card', $data);
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

        $verificationUrl = url('raport/verify/'.$reportCard->verification_token);
        $qrCodeBase64 = $this->generateQrCodeBase64($verificationUrl);
        $homeroomTeacher = $this->resolveHomeroomTeacher($studentId, $semesterId);
        $homeroomSignaturePath = $homeroomTeacher?->homeroom_signature_path;
        $homeroomSignatureAbsolutePath = null;
        if ($homeroomSignaturePath && Storage::disk('public')->exists($homeroomSignaturePath)) {
            $homeroomSignatureAbsolutePath = Storage::disk('public')->path($homeroomSignaturePath);
        }

        return [
            'student' => $student,
            'semester' => $semester,
            'grades' => $grades,
            'violations' => $violations,
            'reportCard' => $reportCard,
            'avgScore' => $avgScore,
            'verificationUrl' => $verificationUrl,
            'qrCodeBase64' => $qrCodeBase64,
            'homeroomTeacherName' => $homeroomTeacher?->name,
            'homeroomSignatureAbsolutePath' => $homeroomSignatureAbsolutePath,
            'principalName' => (string) config('role_certificate.defaults.principal_name'),
            'principalTitle' => (string) config('role_certificate.defaults.principal_title', 'Pimpinan Pondok Pesantren'),
        ];
    }

    private function buildReportData(int $studentId, int $semesterId): array
    {
        $data = $this->buildReportDataForPdf($studentId, $semesterId);
        $data['homeroomHasSignature'] = ! empty($data['homeroomSignatureAbsolutePath']);
        unset($data['qrCodeBase64'], $data['homeroomSignatureAbsolutePath']);

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

    private function resolveHomeroomTeacher(int $studentId, int $semesterId): ?\App\Models\User
    {
        $student = Student::query()->select(['id', 'current_class_id'])->find($studentId);
        if (! $student?->current_class_id) {
            return null;
        }

        $wali = ClassWali::query()
            ->where('class_id', $student->current_class_id)
            ->whereHas('period', fn ($query) => $query->where('semester_id', $semesterId))
            ->with('teacher:id,name,homeroom_signature_path')
            ->latest('id')
            ->first();

        if ($wali?->teacher) {
            return $wali->teacher;
        }

        return ClassWali::query()
            ->where('class_id', $student->current_class_id)
            ->with('teacher:id,name,homeroom_signature_path')
            ->latest('id')
            ->first()?->teacher;
    }
}
