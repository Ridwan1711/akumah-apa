<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Diniyyah\Score;
use App\Models\Diniyyah\SchoolClass;
use App\Models\ReportCard;
use App\Models\ReportCardTemplate;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentViolation;
use App\Notifications\ReportCardPublishedNotification;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ReportCardController extends Controller
{
    public function index(Request $request): Response
    {
        $students = [];

        if ($request->class_id && $request->semester_id) {
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

        return Inertia::render('admin/report-cards/index', [
            'classes' => SchoolClass::query()->orderBy('order')->orderBy('name')->get(['id', 'name', 'grade_level_id']),
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

        $data = $this->buildReportData($request->student_id, $request->semester_id);

        return Inertia::render('admin/report-cards/preview', $data);
    }

    public function saveNotes(Request $request): RedirectResponse
    {
        $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'semester_id' => ['required', 'exists:semesters,id'],
            'wali_kelas_notes' => ['nullable', 'string', 'max:1000'],
        ]);

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

        $reportCard->loadMissing('student.user', 'student.guardians.user');
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

        return redirect()->back()->with('success', 'Catatan raport berhasil disimpan.');
    }

    public function downloadPdf(Request $request)
    {
        $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'semester_id' => ['required', 'exists:semesters,id'],
        ]);

        $data = $this->buildReportDataForPdf($request->student_id, $request->semester_id);

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
        $writer = new PngWriter();
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
