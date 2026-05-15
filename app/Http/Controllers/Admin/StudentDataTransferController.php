<?php

namespace App\Http\Controllers\Admin;

use App\Exports\StudentDataExport;
use App\Exports\StudentTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportStudentsRequest;
use App\Jobs\ProcessImportRun;
use App\Models\ImportRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class StudentDataTransferController extends Controller
{
    public function export(Request $request)
    {
        $format = $request->string('format')->toString() === 'csv' ? 'csv' : 'xlsx';
        $writerType = $format === 'csv'
            ? \Maatwebsite\Excel\Excel::CSV
            : \Maatwebsite\Excel\Excel::XLSX;
        $filename = 'santri-export-'.now()->format('Y-m-d-His').'.'.$format;

        return Excel::download(
            new StudentDataExport($request->only([
                'search',
                'status',
                'class_id',
                'room_id',
                'tingkat_sekolah_id',
                'academic_year_id',
            ])),
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
        $filename = 'template-import-santri-v1.'.$format;

        return Excel::download(new StudentTemplateExport, $filename, $writerType);
    }

    public function import(ImportStudentsRequest $request): RedirectResponse
    {
        $uploadedFile = $request->file('file');
        $storedPath = $uploadedFile->store('imports/uploads', 'local');

        $importRun = ImportRun::query()->create([
            'uuid' => (string) Str::uuid(),
            'type' => ImportRun::TYPE_STUDENTS,
            'job_type' => ImportRun::JOB_STUDENT_IMPORT,
            'strategy' => $request->string('strategy')->toString(),
            'status' => ImportRun::STATUS_QUEUED,
            'requested_by' => $request->user()?->id,
            'file_name' => $uploadedFile->getClientOriginalName(),
            'file_path' => $storedPath,
        ]);

        ProcessImportRun::dispatch($importRun->id);

        return redirect()->route('admin.students.index')
            ->with('success', 'Import santri diproses di background. Anda bisa lanjut kerja, hasil muncul di Riwayat Import.');
    }

    public function downloadErrors(string $token)
    {
        $importRun = ImportRun::query()
            ->where('uuid', $token)
            ->where('type', ImportRun::TYPE_STUDENTS)
            ->firstOrFail();

        abort_if(! $importRun->error_report_path || ! Storage::disk('local')->exists($importRun->error_report_path), 404);

        return response()->download(
            Storage::disk('local')->path($importRun->error_report_path),
            'import-errors-santri-'.$importRun->uuid.'.csv',
            ['Content-Type' => 'text/csv']
        );
    }

    public function retry(ImportRun $importRun): RedirectResponse
    {
        abort_unless($importRun->type === ImportRun::TYPE_STUDENTS, 404);
        abort_unless($importRun->status === ImportRun::STATUS_FAILED, 422, 'Hanya import gagal yang bisa diulang.');
        abort_unless(Storage::disk('local')->exists($importRun->file_path), 404, 'File sumber import tidak ditemukan.');

        $retryRun = ImportRun::query()->create([
            'uuid' => (string) Str::uuid(),
            'type' => $importRun->type,
            'job_type' => $importRun->job_type ?: ImportRun::JOB_STUDENT_IMPORT,
            'strategy' => $importRun->strategy,
            'status' => ImportRun::STATUS_QUEUED,
            'requested_by' => request()->user()?->id,
            'file_name' => $importRun->file_name,
            'file_path' => $importRun->file_path,
            'meta' => ['retry_of' => $importRun->id],
        ]);

        ProcessImportRun::dispatch($retryRun->id);

        return redirect()->route('admin.students.index')
            ->with('success', 'Retry import santri dimasukkan ke antrean background.');
    }
}
