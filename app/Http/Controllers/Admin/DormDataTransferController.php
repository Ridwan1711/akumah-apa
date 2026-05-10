<?php

namespace App\Http\Controllers\Admin;

use App\Exports\DormAssignmentExport;
use App\Exports\DormMasterExport;
use App\Exports\DormTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportDormsRequest;
use App\Imports\DormDataImport;
use App\Models\AcademicYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DormDataTransferController extends Controller
{
    public function template(Request $request)
    {
        $format = $request->string('format')->toString() === 'csv' ? 'csv' : 'xlsx';
        if ($format === 'csv') {
            $writerType = \Maatwebsite\Excel\Excel::CSV;
            $filename = 'template-import-asrama-v1.csv';

            return Excel::download(new DormTemplateGedungKamarSheet, $filename, $writerType);
        }

        $writerType = \Maatwebsite\Excel\Excel::XLSX;
        $filename = 'template-import-asrama-v1.xlsx';

        return Excel::download(new DormTemplateExport, $filename, $writerType);
    }

    public function exportMaster(Request $request)
    {
        $format = $request->string('format')->toString() === 'csv' ? 'csv' : 'xlsx';
        $writerType = $format === 'csv'
            ? \Maatwebsite\Excel\Excel::CSV
            : \Maatwebsite\Excel\Excel::XLSX;
        $filename = 'asrama-master-export-'.now()->format('Y-m-d-His').'.'.$format;

        return Excel::download(new DormMasterExport, $filename, $writerType);
    }

    public function exportAssignments(Request $request)
    {
        $validated = $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
        ]);
        $academicYearId = (int) $validated['academic_year_id'];
        abort_unless(AcademicYear::query()->whereKey($academicYearId)->exists(), 404);

        $format = $request->string('format')->toString() === 'csv' ? 'csv' : 'xlsx';
        $writerType = $format === 'csv'
            ? \Maatwebsite\Excel\Excel::CSV
            : \Maatwebsite\Excel\Excel::XLSX;
        $yearName = (string) (AcademicYear::query()->whereKey($academicYearId)->value('name') ?? $academicYearId);
        $safeName = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $yearName) ?? 'ta';
        $filename = 'asrama-penempatan-'.$safeName.'-'.now()->format('Y-m-d-His').'.'.$format;

        return Excel::download(new DormAssignmentExport($academicYearId), $filename, $writerType);
    }

    public function import(ImportDormsRequest $request): RedirectResponse
    {
        $placementStrategy = $request->input('placement_strategy') === 'replace' ? 'replace' : 'skip';
        $import = new DormDataImport($request->string('strategy')->toString(), $placementStrategy);
        Excel::import($import, $request->file('file'));

        $result = $import->result();
        $summary = sprintf(
            'Import kobong selesai. Diproses: %d, dibuat: %d, diperbarui: %d, dilewati: %d, gagal: %d.',
            $result['processed'],
            $result['created'],
            $result['updated'],
            $result['skipped'],
            $result['failed'],
        );

        $redirect = redirect()->back();

        if (count($result['errors']) > 0) {
            $token = (string) Str::uuid();
            Cache::put('dorm_import_errors:'.$token, $result['errors'], now()->addMinutes(30));
            $redirect->with('dorm_import_error_token', $token);
            $summary .= ' Unduh CSV detail error lewat tautan di bawah (sekali pakai, kedaluwarsa ±30 menit).';
        }

        if ($result['failed'] > 0 || count($result['errors']) > 0) {
            $firstError = $result['errors'][0]['message'] ?? 'Periksa format file import.';

            return $redirect->with('warning', $summary.' Contoh pesan: '.$firstError);
        }

        return $redirect->with('success', $summary);
    }

    /**
     * Unduhan satu-kali daftar baris gagal dari impor Excel (token dari flash `dorm_import_error_token`).
     *
     * QA manual cepat:
     * - Template berisi Gedung_Kamar + Penempatan; sheet harus tepat bernama tersebut.
     * - Penempatan: NIS salah / santri tidak aktif → gagal + baris di CSV error.
     * - Kobong penuh → gagal kapasitas.
     * - placement_strategy=skip + santri sudah punya kobong aktif di TA → skipped.
     * - placement_strategy=replace + santri sudah ada → checkout TA lalu buat baru; target penuh → gagal.
     */
    public function downloadImportErrors(Request $request): StreamedResponse
    {
        $token = $request->query('token', '');
        abort_unless(is_string($token) && preg_match('/^[0-9a-f-]{36}$/i', $token), 404);

        $errors = Cache::pull('dorm_import_errors:'.$token);
        abort_if(! is_array($errors) || $errors === [], 410, 'Daftar error sudah diunduh atau kedaluwarsa. Jalankan impor lagi.');

        $filename = 'asrama-import-errors-'.now()->format('Y-m-d-His').'.csv';

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
