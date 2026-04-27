<?php

namespace App\Http\Controllers\Admin;

use App\Exports\TeacherDataExport;
use App\Exports\TeacherTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportTeachersRequest;
use App\Jobs\ProcessImportRun;
use App\Models\ImportRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class TeacherDataTransferController extends Controller
{
    public function export(Request $request)
    {
        $format = $request->string('format')->toString() === 'csv' ? 'csv' : 'xlsx';
        $writerType = $format === 'csv'
            ? \Maatwebsite\Excel\Excel::CSV
            : \Maatwebsite\Excel\Excel::XLSX;
        $filename = 'guru-export-'.now()->format('Y-m-d-His').'.'.$format;

        return Excel::download(
            new TeacherDataExport($request->only(['search', 'status'])),
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
        $filename = 'template-import-guru-v1.'.$format;

        return Excel::download(new TeacherTemplateExport, $filename, $writerType);
    }

    public function import(ImportTeachersRequest $request): RedirectResponse
    {
        $uploadedFile = $request->file('file');
        $storedPath = $uploadedFile->store('imports/uploads', 'local');

        $importRun = ImportRun::query()->create([
            'uuid' => (string) Str::uuid(),
            'type' => ImportRun::TYPE_TEACHERS,
            'job_type' => ImportRun::JOB_TEACHER_IMPORT,
            'strategy' => $request->string('strategy')->toString(),
            'status' => ImportRun::STATUS_QUEUED,
            'requested_by' => $request->user()?->id,
            'file_name' => $uploadedFile->getClientOriginalName(),
            'file_path' => $storedPath,
        ]);

        ProcessImportRun::dispatch($importRun->id);

        return redirect()->route('admin.users.index', ['role_name' => 'guru'])
            ->with('success', 'Import guru diproses di background. Anda bisa lanjut kerja, hasil muncul di Riwayat Import.');
    }

    public function downloadErrors(string $token)
    {
        $importRun = ImportRun::query()
            ->where('uuid', $token)
            ->where('type', ImportRun::TYPE_TEACHERS)
            ->firstOrFail();

        abort_if(! $importRun->error_report_path || ! Storage::disk('local')->exists($importRun->error_report_path), 404);

        return response()->download(
            Storage::disk('local')->path($importRun->error_report_path),
            'import-errors-guru-'.$importRun->uuid.'.csv',
            ['Content-Type' => 'text/csv']
        );
    }

    public function retry(ImportRun $importRun): RedirectResponse
    {
        abort_unless($importRun->type === ImportRun::TYPE_TEACHERS, 404);
        abort_unless($importRun->status === ImportRun::STATUS_FAILED, 422, 'Hanya import gagal yang bisa diulang.');
        abort_unless(Storage::disk('local')->exists($importRun->file_path), 404, 'File sumber import tidak ditemukan.');

        $retryRun = ImportRun::query()->create([
            'uuid' => (string) Str::uuid(),
            'type' => $importRun->type,
            'job_type' => $importRun->job_type ?: ImportRun::JOB_TEACHER_IMPORT,
            'strategy' => $importRun->strategy,
            'status' => ImportRun::STATUS_QUEUED,
            'requested_by' => request()->user()?->id,
            'file_name' => $importRun->file_name,
            'file_path' => $importRun->file_path,
            'meta' => ['retry_of' => $importRun->id],
        ]);

        ProcessImportRun::dispatch($retryRun->id);

        return redirect()->route('admin.users.index', ['role_name' => 'guru'])
            ->with('success', 'Retry import guru dimasukkan ke antrean background.');
    }
}
