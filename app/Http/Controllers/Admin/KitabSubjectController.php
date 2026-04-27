<?php

namespace App\Http\Controllers\Admin;

use App\Exports\KitabSubjectDataExport;
use App\Exports\KitabSubjectTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportKitabSubjectsRequest;
use App\Http\Requests\Admin\StoreKitabSubjectRequest;
use App\Imports\KitabSubjectDataImport;
use App\Models\Diniyyah\Fan;
use App\Models\Diniyyah\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class KitabSubjectController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('admin/kitab-subjects/index', [
            'subjects' => Subject::query()
                ->with('fan:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'fan_id']),
            'fans' => Fan::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function export(Request $request)
    {
        $format = $request->string('format')->toString() === 'csv' ? 'csv' : 'xlsx';
        $writerType = $format === 'csv'
            ? \Maatwebsite\Excel\Excel::CSV
            : \Maatwebsite\Excel\Excel::XLSX;
        $filename = 'kitab-subjects-export-'.now()->format('Y-m-d-His').'.'.$format;

        return Excel::download(new KitabSubjectDataExport, $filename, $writerType);
    }

    public function template(Request $request)
    {
        $format = $request->string('format')->toString() === 'csv' ? 'csv' : 'xlsx';
        $writerType = $format === 'csv'
            ? \Maatwebsite\Excel\Excel::CSV
            : \Maatwebsite\Excel\Excel::XLSX;
        $filename = 'template-import-mapel-kitab-v1.'.$format;

        return Excel::download(new KitabSubjectTemplateExport, $filename, $writerType);
    }

    public function import(ImportKitabSubjectsRequest $request): RedirectResponse
    {
        $import = new KitabSubjectDataImport($request->string('strategy')->toString());
        Excel::import($import, $request->file('file'));

        $result = $import->result();
        $summary = sprintf(
            'Import mapel selesai. Diproses: %d, dibuat: %d, diperbarui: %d, dilewati: %d, gagal: %d.',
            $result['processed'],
            $result['created'],
            $result['updated'],
            $result['skipped'],
            $result['failed']
        );

        if ($result['failed'] > 0) {
            $firstError = $result['errors'][0]['message'] ?? 'Periksa format data import Anda.';

            return redirect()->route('admin.kitab-subjects.index')
                ->with('error', $summary.' Error pertama: '.$firstError);
        }

        return redirect()->route('admin.kitab-subjects.index')
            ->with('success', $summary);
    }

    public function store(StoreKitabSubjectRequest $request): RedirectResponse
    {
        Subject::query()->create($request->validated());

        return redirect()->route('admin.kitab-subjects.index')
            ->with('success', 'Mata pelajaran berhasil ditambahkan.');
    }

    public function update(StoreKitabSubjectRequest $request, Subject $kitabSubject): RedirectResponse
    {
        $kitabSubject->update($request->validated());

        return redirect()->route('admin.kitab-subjects.index')
            ->with('success', 'Mata pelajaran berhasil diperbarui.');
    }

    public function destroy(Subject $kitabSubject): RedirectResponse
    {
        $kitabSubject->delete();

        return redirect()->route('admin.kitab-subjects.index')
            ->with('success', 'Mata pelajaran berhasil dihapus.');
    }
}
