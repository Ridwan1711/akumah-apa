<?php

namespace App\Http\Controllers\Admin;

use App\Exports\InvoiceDataExport;
use App\Exports\InvoiceTemplateDataSheet;
use App\Exports\InvoiceTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportInvoicesRequest;
use App\Jobs\ProcessImportRun;
use App\Models\ImportRun;
use App\Services\Authorization\InvoiceVisibilityScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class InvoiceDataTransferController extends Controller
{
    public function __construct(
        private InvoiceVisibilityScope $invoiceVisibilityScope,
    ) {}

    public function template(Request $request)
    {
        $format = $request->string('format')->toString() === 'csv' ? 'csv' : 'xlsx';
        if ($format === 'csv') {
            return Excel::download(
                new InvoiceTemplateDataSheet,
                'template-import-tagihan-v1.csv',
                \Maatwebsite\Excel\Excel::CSV
            );
        }

        return Excel::download(
            new InvoiceTemplateExport,
            'template-import-tagihan-v1.xlsx',
            \Maatwebsite\Excel\Excel::XLSX
        );
    }

    public function export(Request $request)
    {
        $format = $request->string('format')->toString() === 'csv' ? 'csv' : 'xlsx';
        $writerType = $format === 'csv'
            ? \Maatwebsite\Excel\Excel::CSV
            : \Maatwebsite\Excel\Excel::XLSX;
        $filename = 'tagihan-export-'.now()->format('Y-m-d-His').'.'.$format;

        return Excel::download(
            new InvoiceDataExport($request, $this->invoiceVisibilityScope),
            $filename,
            $writerType
        );
    }

    public function import(ImportInvoicesRequest $request): RedirectResponse
    {
        $uploadedFile = $request->file('file');
        $storedPath = $uploadedFile->store('imports/uploads', 'local');

        $importRun = ImportRun::query()->create([
            'uuid' => (string) Str::uuid(),
            'type' => ImportRun::TYPE_INVOICES,
            'job_type' => ImportRun::JOB_INVOICE_IMPORT,
            'strategy' => $request->string('strategy')->toString(),
            'status' => ImportRun::STATUS_QUEUED,
            'requested_by' => $request->user()?->id,
            'file_name' => $uploadedFile->getClientOriginalName(),
            'file_path' => $storedPath,
        ]);

        ProcessImportRun::dispatch($importRun->id);

        return redirect()->route('admin.invoices.index')
            ->with('success', 'Import tagihan diproses di background. Refresh halaman untuk melihat progres.');
    }

    public function downloadErrors(string $token)
    {
        $importRun = ImportRun::query()
            ->where('uuid', $token)
            ->where('type', ImportRun::TYPE_INVOICES)
            ->firstOrFail();

        abort_if(! $importRun->error_report_path || ! Storage::disk('local')->exists($importRun->error_report_path), 404);

        return response()->download(
            Storage::disk('local')->path($importRun->error_report_path),
            'import-errors-tagihan-'.$importRun->uuid.'.csv',
            ['Content-Type' => 'text/csv']
        );
    }

    public function retry(ImportRun $importRun): RedirectResponse
    {
        abort_unless($importRun->type === ImportRun::TYPE_INVOICES, 404);
        abort_unless($importRun->job_type === ImportRun::JOB_INVOICE_IMPORT, 404);
        abort_unless($importRun->status === ImportRun::STATUS_FAILED, 422, 'Hanya import gagal yang bisa diulang.');
        abort_unless(Storage::disk('local')->exists($importRun->file_path), 404, 'File sumber import tidak ditemukan.');

        $retryRun = ImportRun::query()->create([
            'uuid' => (string) Str::uuid(),
            'type' => $importRun->type,
            'job_type' => $importRun->job_type ?: ImportRun::JOB_INVOICE_IMPORT,
            'strategy' => $importRun->strategy,
            'status' => ImportRun::STATUS_QUEUED,
            'requested_by' => request()->user()?->id,
            'file_name' => $importRun->file_name,
            'file_path' => $importRun->file_path,
            'meta' => ['retry_of' => $importRun->id],
        ]);

        ProcessImportRun::dispatch($retryRun->id);

        return redirect()->route('admin.invoices.index')
            ->with('success', 'Retry import tagihan dimasukkan ke antrean background.');
    }
}
