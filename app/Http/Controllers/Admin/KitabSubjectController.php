<?php

namespace App\Http\Controllers\Admin;

use App\Exports\KitabSubjectDataExport;
use App\Exports\KitabSubjectTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportKitabSubjectsRequest;
use App\Http\Requests\Admin\StoreKitabSubjectRequest;
use App\Imports\KitabSubjectDataImport;
use App\Models\Diniyyah\GradeLevel;
use App\Models\Diniyyah\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class KitabSubjectController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('admin/kitab-subjects/index', [
            'subjects' => Subject::query()
                ->with(['aliases' => fn ($query) => $query
                    ->with('tingkat:id,name,order')
                    ->orderBy('tingkat_id')])
                ->orderBy('name')
                ->get(['id', 'name']),
            'tingkats' => GradeLevel::query()
                ->orderBy('order')
                ->orderBy('name')
                ->get(['id', 'name', 'order']),
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
        $validated = $request->validated();

        DB::transaction(function () use ($validated) {
            $subject = Subject::query()->create([
                'name' => $validated['name'],
            ]);

            $aliasPayload = collect($validated['aliases'] ?? [])
                ->filter(fn (array $alias) => isset($alias['tingkat_id']))
                ->mapWithKeys(fn (array $alias) => [
                    (int) $alias['tingkat_id'] => [
                        'alias_name' => filled($alias['alias_name'] ?? null)
                            ? (string) $alias['alias_name']
                            : null,
                    ],
                ]);

            if ($aliasPayload->isNotEmpty()) {
                $rows = $aliasPayload->map(fn (array $payload, int $tingkatId) => [
                    'subject_id' => $subject->id,
                    'tingkat_id' => $tingkatId,
                    'alias_name' => $payload['alias_name'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->values()->all();
                DB::table('subject_aliases')->insert($rows);
            }
        });

        return redirect()->route('admin.kitab-subjects.index')
            ->with('success', 'Mata pelajaran berhasil ditambahkan.');
    }

    public function update(StoreKitabSubjectRequest $request, Subject $kitabSubject): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($kitabSubject, $validated) {
            $kitabSubject->update([
                'name' => $validated['name'],
            ]);

            $aliasPayload = collect($validated['aliases'] ?? [])
                ->filter(fn (array $alias) => isset($alias['tingkat_id']))
                ->mapWithKeys(fn (array $alias) => [
                    (int) $alias['tingkat_id'] => [
                        'alias_name' => filled($alias['alias_name'] ?? null)
                            ? (string) $alias['alias_name']
                            : null,
                    ],
                ])
                ->all();

            $kitabSubject->aliases()->delete();
            if ($aliasPayload !== []) {
                $rows = collect($aliasPayload)->map(fn (array $payload, int $tingkatId) => [
                    'subject_id' => $kitabSubject->id,
                    'tingkat_id' => $tingkatId,
                    'alias_name' => $payload['alias_name'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->values()->all();
                DB::table('subject_aliases')->insert($rows);
            }
        });

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
