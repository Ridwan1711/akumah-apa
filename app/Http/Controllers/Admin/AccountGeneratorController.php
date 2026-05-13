<?php

namespace App\Http\Controllers\Admin;

use App\Exports\MasterAccountCredentialsExport;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessBulkRun;
use App\Models\Guardian;
use App\Models\ImportRun;
use App\Models\Student;
use App\Models\User;
use App\Services\AccountGenerator\AccountGenerateRollbackService;
use App\Services\AccountGenerator\MasterCredentialsAggregator;
use App\Support\AccountGeneratorCredentialsFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class AccountGeneratorController extends Controller
{
    public function index(Request $request): Response
    {
        $perPageOptions = [25, 50, 75, 100, 150];
        $perPage = (int) $request->input('per_page', 25);
        if (! in_array($perPage, $perPageOptions, true)) {
            $perPage = 25;
        }

        $studentsWithoutAccount = Student::whereNull('user_id')
            ->where('status', Student::STATUS_ACTIVE)
            ->orderBy('full_name')
            ->paginate($perPage, ['id', 'nis', 'full_name'], 'students_page')
            ->withQueryString();

        $guardiansWithoutAccount = Guardian::whereNull('user_id')
            ->whereHas('students')
            ->with('students:id,nis,full_name')
            ->orderBy('full_name')
            ->paginate($perPage, ['id', 'full_name'], 'guardians_page')
            ->withQueryString()
            ->through(function ($g) {
                $first = $g->students->first();
                $g->student = $first;
                $g->relationship = $first?->pivot?->relationship ?? 'wali';
                $g->student_id = $first?->id;

                return $g;
            });

        $bulkRunsQuery = ImportRun::query()
            ->with('requestedBy:id,name')
            ->whereIn('job_type', [
                ImportRun::JOB_ACCOUNT_GENERATE_STUDENTS,
                ImportRun::JOB_ACCOUNT_GENERATE_GUARDIANS,
            ])
            ->when($request->run_uploader_id, fn ($q, $id) => $q->where('requested_by', $id))
            ->latest('id');

        $uploaderIds = ImportRun::query()
            ->whereIn('job_type', [
                ImportRun::JOB_ACCOUNT_GENERATE_STUDENTS,
                ImportRun::JOB_ACCOUNT_GENERATE_GUARDIANS,
            ])
            ->whereNotNull('requested_by')
            ->distinct()
            ->pluck('requested_by');

        return Inertia::render('admin/account-generator/index', [
            'studentsWithoutAccount' => $studentsWithoutAccount,
            'guardiansWithoutAccount' => $guardiansWithoutAccount,
            'bulkRuns' => $bulkRunsQuery->limit(20)->get(),
            'bulkUploaders' => User::query()->whereIn('id', $uploaderIds)->orderBy('name')->get(['id', 'name']),
            'runFilters' => $request->only(['run_uploader_id', 'per_page']),
            'perPageOptions' => $perPageOptions,
            'isSuperAdmin' => $request->user()?->isSuperAdmin() ?? false,
            'accountGenerateBatchLimits' => [
                'maxStudentsPerRun' => ImportRun::ACCOUNT_BATCH_MAX_STUDENTS,
                'maxGuardiansPerRun' => ImportRun::ACCOUNT_BATCH_MAX_GUARDIANS,
            ],
        ]);
    }

    public function generateStudentAccounts(Request $request)
    {
        $validated = $request->validate([
            'student_ids' => ['required', 'array', 'min:1', 'max:'.ImportRun::ACCOUNT_BATCH_MAX_STUDENTS],
            'student_ids.*' => ['exists:students,id'],
            'include_wali_accounts' => ['sometimes', 'boolean'],
        ]);

        $run = ImportRun::query()->create([
            'uuid' => (string) Str::uuid(),
            'type' => ImportRun::TYPE_BULK,
            'job_type' => ImportRun::JOB_ACCOUNT_GENERATE_STUDENTS,
            'status' => ImportRun::STATUS_QUEUED,
            'requested_by' => $request->user()?->id,
            'file_name' => 'account-generate-students',
            'file_path' => '-',
            'meta' => [
                'student_ids' => $request->student_ids,
                'include_wali_accounts' => array_key_exists('include_wali_accounts', $validated)
                    ? (bool) $validated['include_wali_accounts']
                    : true,
            ],
        ]);

        ProcessBulkRun::dispatch($run->id);

        return back()->with('success', 'Generate akun santri diproses di background.');
    }

    public function previewWaliImpact(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_ids' => ['required', 'array', 'min:1', 'max:'.ImportRun::ACCOUNT_BATCH_MAX_STUDENTS],
            'student_ids.*' => ['exists:students,id'],
        ]);

        $studentIds = $validated['student_ids'];

        $students = Student::query()
            ->whereIn('id', $studentIds)
            ->with(['guardians' => function ($q) {
                $q->select('guardians.id', 'guardians.full_name', 'guardians.user_id');
            }])
            ->orderBy('full_name')
            ->get(['id', 'nis', 'full_name']);

        $byGuardian = [];

        foreach ($students as $student) {
            foreach ($student->guardians as $guardian) {
                $gid = $guardian->id;
                if (! isset($byGuardian[$gid])) {
                    $byGuardian[$gid] = [
                        'id' => $gid,
                        'full_name' => $guardian->full_name,
                        'user_id' => $guardian->user_id,
                        'already_has_account' => $guardian->user_id !== null,
                        'students_in_selection' => [],
                    ];
                }
                $byGuardian[$gid]['students_in_selection'][] = [
                    'id' => $student->id,
                    'full_name' => $student->full_name,
                    'nis' => $student->nis,
                    'relationship' => $guardian->pivot->relationship ?? null,
                ];
            }
        }

        $guardianIds = array_keys($byGuardian);
        $counts = $guardianIds === []
            ? collect()
            : Guardian::query()
                ->whereIn('id', $guardianIds)
                ->withCount('students')
                ->get()
                ->keyBy('id');

        $guardiansOut = [];
        foreach ($byGuardian as $gid => $row) {
            $inSel = $row['students_in_selection'];
            $relationship = collect($inSel)->pluck('relationship')->filter()->first();

            $guardiansOut[] = [
                'id' => $row['id'],
                'full_name' => $row['full_name'],
                'relationship' => $relationship,
                'already_has_account' => $row['already_has_account'],
                'is_shared_in_selection' => count($inSel) > 1,
                'students_in_selection' => $inSel,
                'total_children_in_db' => (int) ($counts->get($gid)?->students_count ?? 0),
            ];
        }

        usort($guardiansOut, function (array $a, array $b) {
            if ($a['is_shared_in_selection'] !== $b['is_shared_in_selection']) {
                return $a['is_shared_in_selection'] ? -1 : 1;
            }

            return strcmp($a['full_name'], $b['full_name']);
        });

        $studentsWithoutGuardians = $students
            ->filter(fn (Student $s) => $s->guardians->isEmpty())
            ->values()
            ->map(fn (Student $s) => [
                'id' => $s->id,
                'full_name' => $s->full_name,
                'nis' => $s->nis,
            ])
            ->all();

        return response()->json([
            'selection_count' => $students->count(),
            'guardians' => $guardiansOut,
            'students_without_guardians' => $studentsWithoutGuardians,
        ]);
    }

    public function generateGuardianAccounts(Request $request)
    {
        $request->validate([
            'guardian_ids' => ['required', 'array', 'min:1', 'max:'.ImportRun::ACCOUNT_BATCH_MAX_GUARDIANS],
            'guardian_ids.*' => ['exists:guardians,id'],
        ]);

        $run = ImportRun::query()->create([
            'uuid' => (string) Str::uuid(),
            'type' => ImportRun::TYPE_BULK,
            'job_type' => ImportRun::JOB_ACCOUNT_GENERATE_GUARDIANS,
            'status' => ImportRun::STATUS_QUEUED,
            'requested_by' => $request->user()?->id,
            'file_name' => 'account-generate-guardians',
            'file_path' => '-',
            'meta' => [
                'guardian_ids' => $request->guardian_ids,
            ],
        ]);

        ProcessBulkRun::dispatch($run->id);

        return back()->with('success', 'Generate akun wali diproses di background.');
    }

    public function retryBulkRun(ImportRun $importRun)
    {
        abort_unless(in_array($importRun->job_type, [
            ImportRun::JOB_ACCOUNT_GENERATE_STUDENTS,
            ImportRun::JOB_ACCOUNT_GENERATE_GUARDIANS,
        ], true), 404);
        abort_unless($importRun->status === ImportRun::STATUS_FAILED, 422, 'Hanya job gagal yang bisa di-retry.');

        $retryRun = ImportRun::query()->create([
            'uuid' => (string) Str::uuid(),
            'type' => ImportRun::TYPE_BULK,
            'job_type' => $importRun->job_type,
            'status' => ImportRun::STATUS_QUEUED,
            'requested_by' => request()->user()?->id,
            'file_name' => $importRun->file_name,
            'file_path' => '-',
            'meta' => $importRun->meta,
        ]);

        ProcessBulkRun::dispatch($retryRun->id);

        return back()->with('success', 'Retry generate akun diproses di background.');
    }

    public function rollbackAccountGenerateRun(Request $request, ImportRun $importRun, AccountGenerateRollbackService $rollbackService)
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        abort_unless(in_array($importRun->job_type, [
            ImportRun::JOB_ACCOUNT_GENERATE_STUDENTS,
            ImportRun::JOB_ACCOUNT_GENERATE_GUARDIANS,
        ], true), 404);

        $request->validate([
            'confirm' => ['required', 'string', 'in:ROLLBACK'],
        ]);

        try {
            $summary = $rollbackService->rollback($importRun);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        $msg = sprintf(
            'Rollback selesai: %d akun santri dan %d akun wali dihapus (user + link ke santri/wali).',
            $summary['santri_removed'],
            $summary['wali_removed']
        );
        if ($summary['warnings'] !== []) {
            $msg .= ' '.implode(' ', $summary['warnings']);
        }

        return back()->with('success', $msg);
    }

    /**
     * Unduh TSV lengkap kredensial hasil generate (menghindari batas clipboard & JSON).
     */
    public function downloadCredentialsExport(ImportRun $importRun)
    {
        abort_unless(in_array($importRun->job_type, [
            ImportRun::JOB_ACCOUNT_GENERATE_STUDENTS,
            ImportRun::JOB_ACCOUNT_GENERATE_GUARDIANS,
        ], true), 404);

        abort_unless($importRun->status === ImportRun::STATUS_COMPLETED, 422, 'Job belum selesai.');

        $payload = $importRun->result_payload;
        if (! is_array($payload)) {
            abort(404, 'Tidak ada data kredensial.');
        }

        $relPath = isset($payload['credentials_full_export_path']) && is_string($payload['credentials_full_export_path'])
            ? $payload['credentials_full_export_path']
            : null;

        if ($relPath !== null && $relPath !== '' && Storage::disk('local')->exists($relPath)) {
            return Storage::disk('local')->download(
                $relPath,
                'kredensial-generate-'.$importRun->id.'.tsv',
                ['Content-Type' => 'text/tab-separated-values; charset=UTF-8']
            );
        }

        $merged = AccountGeneratorCredentialsFormatter::mergeFromPayload($payload);
        if ($merged === []) {
            abort(404, 'Tidak ada kredensial untuk diunduh.');
        }

        $tsv = AccountGeneratorCredentialsFormatter::toTsv($merged);
        $filename = 'kredensial-generate-'.$importRun->id.'.tsv';

        return response("\xEF\xBB\xBF".$tsv, 200, [
            'Content-Type' => 'text/tab-separated-values; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Gabungan semua kredensial plaintext dari job generate akun (file + payload). Hanya Super Admin.
     */
    public function downloadMasterCredentialsXlsx(Request $request)
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $rows = MasterCredentialsAggregator::aggregate();
        if ($rows === []) {
            abort(404, 'Belum ada kredensial hasil generate akun yang tersimpan untuk digabung. Jalankan generate akun setelah update ini agar file export tersimpan di server.');
        }

        $filename = 'master-kredensial-generate-akun-'.now()->format('Y-m-d_His').'.xlsx';

        return Excel::download(
            new MasterAccountCredentialsExport($rows),
            $filename,
            \Maatwebsite\Excel\Excel::XLSX
        );
    }
}
