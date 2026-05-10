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
use Maatwebsite\Excel\Facades\Excel;

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
        $import = new DormDataImport($request->string('strategy')->toString());
        Excel::import($import, $request->file('file'));

        $result = $import->result();
        $summary = sprintf(
            'Import asrama selesai. Diproses: %d, dibuat: %d, diperbarui: %d, dilewati: %d, gagal: %d.',
            $result['processed'],
            $result['created'],
            $result['updated'],
            $result['skipped'],
            $result['failed'],
        );

        if ($result['failed'] > 0 || count($result['errors']) > 0) {
            $firstError = $result['errors'][0]['message'] ?? 'Periksa format file import.';

            return redirect()->back()
                ->with('warning', $summary.' Detail: '.$firstError);
        }

        return redirect()->back()
            ->with('success', $summary);
    }
}
