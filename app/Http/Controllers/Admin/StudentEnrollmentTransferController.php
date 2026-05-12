<?php

namespace App\Http\Controllers\Admin;

use App\Exports\EnrollmentTemplateExport;
use App\Exports\StudentEnrollmentDataExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportEnrollmentsRequest;
use App\Jobs\ProcessImportRun;
use App\Models\AcademicPeriod;
use App\Models\ImportRun;
use App\Models\Semester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class StudentEnrollmentTransferController extends Controller
{
    public function export(Request $request)
    {
        $periodId = (int) $request->integer('period_id');
        if ($periodId <= 0) {
            $semesterId = (int) $request->integer('semester_id');
            abort_if($semesterId <= 0, 422, 'Pilih periode akademik (period_id) atau semester sebelum export enrollment.');

            $periodId = $this->resolvePeriodIdBySemesterId($semesterId);
        } else {
            abort_unless(AcademicPeriod::query()->whereKey($periodId)->exists(), 422, 'Periode akademik tidak valid.');
        }
        $format = $request->string('format')->toString() === 'csv' ? 'csv' : 'xlsx';
        $writerType = $format === 'csv'
            ? \Maatwebsite\Excel\Excel::CSV
            : \Maatwebsite\Excel\Excel::XLSX;
        $filename = 'enrollment-export-'.now()->format('Y-m-d-His').'.'.$format;

        return Excel::download(
            new StudentEnrollmentDataExport(
                $periodId,
                $request->only(['search', 'status', 'class_id'])
            ),
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
        $filename = 'template-import-enrollment-v1.'.$format;

        return Excel::download(new EnrollmentTemplateExport, $filename, $writerType);
    }

    public function import(ImportEnrollmentsRequest $request): RedirectResponse
    {
        $uploadedFile = $request->file('file');
        $storedPath = $uploadedFile->store('imports/uploads', 'local');

        $importRun = ImportRun::query()->create([
            'uuid' => (string) Str::uuid(),
            'type' => ImportRun::TYPE_ENROLLMENTS,
            'job_type' => ImportRun::JOB_ENROLLMENT_IMPORT,
            'strategy' => $request->string('strategy')->toString(),
            'status' => ImportRun::STATUS_QUEUED,
            'requested_by' => $request->user()?->id,
            'file_name' => $uploadedFile->getClientOriginalName(),
            'file_path' => $storedPath,
        ]);

        ProcessImportRun::dispatch($importRun->id);

        return redirect()->route('admin.student-enrollments.index')
            ->with('success', 'Import enrollment diproses di background.');
    }

    public function downloadErrors(string $token)
    {
        $importRun = ImportRun::query()
            ->where('uuid', $token)
            ->where('type', ImportRun::TYPE_ENROLLMENTS)
            ->firstOrFail();

        abort_if(! $importRun->error_report_path || ! Storage::disk('local')->exists($importRun->error_report_path), 404);

        return response()->download(
            Storage::disk('local')->path($importRun->error_report_path),
            'import-errors-enrollment-'.$importRun->uuid.'.csv',
            ['Content-Type' => 'text/csv']
        );
    }

    public function retry(ImportRun $importRun): RedirectResponse
    {
        abort_unless($importRun->type === ImportRun::TYPE_ENROLLMENTS, 404);
        abort_unless($importRun->status === ImportRun::STATUS_FAILED, 422, 'Hanya import gagal yang bisa diulang.');
        abort_unless(Storage::disk('local')->exists($importRun->file_path), 404, 'File sumber import tidak ditemukan.');

        $retryRun = ImportRun::query()->create([
            'uuid' => (string) Str::uuid(),
            'type' => $importRun->type,
            'job_type' => $importRun->job_type ?: ImportRun::JOB_ENROLLMENT_IMPORT,
            'strategy' => $importRun->strategy,
            'status' => ImportRun::STATUS_QUEUED,
            'requested_by' => request()->user()?->id,
            'file_name' => $importRun->file_name,
            'file_path' => $importRun->file_path,
            'meta' => ['retry_of' => $importRun->id],
        ]);

        ProcessImportRun::dispatch($retryRun->id);

        return redirect()->route('admin.student-enrollments.index')
            ->with('success', 'Retry import enrollment dimasukkan ke antrean background.');
    }

    protected function resolvePeriodIdBySemesterId(int $semesterId): int
    {
        $periodId = (int) AcademicPeriod::query()
            ->where('semester_id', $semesterId)
            ->value('id');

        if ($periodId > 0) {
            return $periodId;
        }

        $semester = Semester::query()->findOrFail($semesterId);
        $normalizedName = strtolower($semester->name);
        $isSemesterTwo = str_contains($normalizedName, 'genap')
            || str_contains($normalizedName, '2')
            || str_contains($normalizedName, 'ii');

        $period = AcademicPeriod::query()->create([
            'name' => $semester->name,
            'type' => $isSemesterTwo ? AcademicPeriod::TYPE_SEMESTER_2 : AcademicPeriod::TYPE_SEMESTER_1,
            'is_active' => false,
            'semester_id' => $semester->id,
        ]);

        return (int) $period->id;
    }
}
