<?php

namespace App\Http\Controllers\Admin;

use App\Exports\FormalTingkatEnrollmentExport;
use App\Exports\FormalTingkatReferensiSheet;
use App\Exports\FormalTingkatTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportFormalTingkatRequest;
use App\Imports\FormalTingkatDataImport;
use App\Models\AcademicYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FormalTingkatTransferController extends Controller
{
    public function template(Request $request)
    {
        $format = $request->string('format')->toString() === 'csv' ? 'csv' : 'xlsx';
        if ($format === 'csv') {
            return Excel::download(new FormalTingkatReferensiSheet, 'template-tingkat-formal-referensi-v1.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return Excel::download(new FormalTingkatTemplateExport, 'template-tingkat-formal-v1.xlsx', \Maatwebsite\Excel\Excel::XLSX);
    }

    public function exportMaster(Request $request)
    {
        $format = $request->string('format')->toString() === 'csv' ? 'csv' : 'xlsx';
        $writerType = $format === 'csv'
            ? \Maatwebsite\Excel\Excel::CSV
            : \Maatwebsite\Excel\Excel::XLSX;
        $filename = 'tingkat-formal-master-'.now()->format('Y-m-d-His').'.'.$format;

        return Excel::download(new FormalTingkatReferensiSheet, $filename, $writerType);
    }

    public function exportEnrollments(Request $request)
    {
        $validated = $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
        ]);
        $academicYearId = (int) $validated['academic_year_id'];

        $format = $request->string('format')->toString() === 'csv' ? 'csv' : 'xlsx';
        $writerType = $format === 'csv'
            ? \Maatwebsite\Excel\Excel::CSV
            : \Maatwebsite\Excel\Excel::XLSX;
        $yearName = (string) (AcademicYear::query()->whereKey($academicYearId)->value('name') ?? $academicYearId);
        $safeName = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $yearName) ?? 'ta';
        $filename = 'enrollment-tingkat-formal-'.$safeName.'-'.now()->format('Y-m-d-His').'.'.$format;

        return Excel::download(new FormalTingkatEnrollmentExport($academicYearId), $filename, $writerType);
    }

    public function import(ImportFormalTingkatRequest $request): RedirectResponse
    {
        $strategy = $request->input('enrollment_strategy') === 'replace' ? 'replace' : 'skip';
        $import = new FormalTingkatDataImport($strategy);
        Excel::import($import, $request->file('file'));

        $result = $import->result();
        $summary = sprintf(
            'Import enrollment tingkat formal selesai. Diproses: %d, dibuat: %d, diperbarui: %d, dilewati: %d, gagal: %d.',
            $result['processed'],
            $result['created'],
            $result['updated'],
            $result['skipped'],
            $result['failed'],
        );

        $redirect = redirect()->back();

        if (count($result['errors']) > 0) {
            $token = (string) Str::uuid();
            Cache::put('formal_tingkat_import_errors:'.$token, $result['errors'], now()->addMinutes(30));
            $redirect->with('formal_tingkat_import_error_token', $token);
            $summary .= ' Unduh CSV detail error lewat tautan di bawah (sekali pakai, ±30 menit).';
        }

        if ($result['failed'] > 0 || count($result['errors']) > 0) {
            $firstError = $result['errors'][0]['message'] ?? 'Periksa format file import.';

            return $redirect->with('warning', $summary.' Contoh pesan: '.$firstError);
        }

        return $redirect->with('success', $summary);
    }

    public function downloadImportErrors(Request $request): StreamedResponse
    {
        $token = $request->query('token', '');
        abort_unless(is_string($token) && preg_match('/^[0-9a-f-]{36}$/i', $token), 404);

        $errors = Cache::pull('formal_tingkat_import_errors:'.$token);
        abort_if(! is_array($errors) || $errors === [], 410, 'Daftar error sudah diunduh atau kedaluwarsa. Jalankan impor lagi.');

        $filename = 'tingkat-formal-import-errors-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($errors): void {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, ['sheet_row', 'message']);
            foreach ($errors as $row) {
                if (! is_array($row)) {
                    continue;
                }
                fputcsv($handle, [
                    isset($row['row']) ? (string) $row['row'] : '',
                    isset($row['message']) ? (string) $row['message'] : '',
                ]);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
