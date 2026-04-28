<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessBulkRun;
use App\Jobs\ProcessImportRun;
use App\Models\ImportRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class QueueRunController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'scope' => ['nullable', 'in:my,all'],
        ]);

        $limit = (int) ($validated['limit'] ?? 15);
        $scope = $validated['scope'] ?? 'my';
        $user = $request->user();
        $canViewAll = $user->isAdmin();

        $runsQuery = ImportRun::query()
            ->with('requestedBy:id,name')
            ->latest('id')
            ->limit($limit);

        if (! $canViewAll || $scope !== 'all') {
            $runsQuery->where('requested_by', $user->id);
        }

        $runs = $runsQuery->get([
                'id',
                'uuid',
                'type',
                'job_type',
                'status',
                'requested_by',
                'file_name',
                'file_path',
                'strategy',
                'meta',
                'total_rows',
                'processed_rows',
                'created_count',
                'updated_count',
                'skipped_count',
                'failed_count',
                'error_message',
                'started_at',
                'finished_at',
                'created_at',
            ]);

        $activeCount = $runs
            ->whereIn('status', [ImportRun::STATUS_QUEUED, ImportRun::STATUS_PROCESSING])
            ->count();

        return response()->json([
            'data' => $runs->map(fn (ImportRun $run) => [
                'id' => $run->id,
                'uuid' => $run->uuid,
                'title' => $this->jobTitle($run->job_type, $run->type),
                'status' => $run->status,
                'job_type' => $run->job_type,
                'type' => $run->type,
                'file_name' => $run->file_name,
                'requested_by' => $run->requestedBy?->name,
                'progress_percent' => $this->progressPercent($run),
                'processed_rows' => (int) $run->processed_rows,
                'total_rows' => (int) $run->total_rows,
                'created_count' => (int) $run->created_count,
                'updated_count' => (int) $run->updated_count,
                'skipped_count' => (int) $run->skipped_count,
                'failed_count' => (int) $run->failed_count,
                'error_message' => $run->error_message,
                'created_at' => $run->created_at?->diffForHumans(),
                'started_at' => $run->started_at?->toIso8601String(),
                'finished_at' => $run->finished_at?->toIso8601String(),
                'can_retry' => $this->canRetry($run),
            ]),
            'meta' => [
                'active_count' => $activeCount,
                'can_view_all' => $canViewAll,
                'current_scope' => $canViewAll ? $scope : 'my',
            ],
        ]);
    }

    public function retry(Request $request, ImportRun $importRun): JsonResponse
    {
        abort_unless($this->canRetry($importRun), 422, 'Hanya proses gagal yang bisa diulang.');

        if ($importRun->requested_by !== $request->user()->id && ! $request->user()->isAdmin()) {
            abort(403, 'Anda tidak memiliki akses untuk retry proses ini.');
        }

        if ($this->requiresSourceFile($importRun) && ! Storage::disk('local')->exists((string) $importRun->file_path)) {
            abort(404, 'File sumber proses tidak ditemukan.');
        }

        $meta = is_array($importRun->meta) ? $importRun->meta : [];
        $meta['retry_of'] = $importRun->id;

        $retryRun = ImportRun::query()->create([
            'uuid' => (string) Str::uuid(),
            'type' => $importRun->type,
            'job_type' => $importRun->job_type,
            'strategy' => $importRun->strategy,
            'status' => ImportRun::STATUS_QUEUED,
            'requested_by' => $request->user()->id,
            'file_name' => $importRun->file_name,
            'file_path' => $importRun->file_path,
            'meta' => $meta,
        ]);

        if ($importRun->type === ImportRun::TYPE_BULK) {
            ProcessBulkRun::dispatch($retryRun->id);
        } else {
            ProcessImportRun::dispatch($retryRun->id);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Retry berhasil dimasukkan ke antrean background.',
            'run' => [
                'id' => $retryRun->id,
                'uuid' => $retryRun->uuid,
                'status' => $retryRun->status,
            ],
        ]);
    }

    private function progressPercent(ImportRun $run): int
    {
        if ($run->status === ImportRun::STATUS_COMPLETED) {
            return 100;
        }

        $total = max(0, (int) $run->total_rows);
        $processed = max(0, (int) $run->processed_rows);

        if ($total <= 0) {
            return $run->status === ImportRun::STATUS_QUEUED ? 0 : min(99, $processed > 0 ? 10 : 0);
        }

        return max(0, min(100, (int) round(($processed / $total) * 100)));
    }

    private function jobTitle(?string $jobType, string $type): string
    {
        return match ($jobType) {
            ImportRun::JOB_STUDENT_IMPORT => 'Import Data Santri',
            ImportRun::JOB_TEACHER_IMPORT => 'Import Data Guru',
            ImportRun::JOB_ENROLLMENT_IMPORT => 'Import Enroll Kelas',
            ImportRun::JOB_INVOICE_BULK_GENERATE => 'Generate Tagihan Massal',
            ImportRun::JOB_CLASS_PROMOTION => 'Proses Kenaikan Kelas',
            ImportRun::JOB_ACCOUNT_GENERATE_STUDENTS => 'Generate Akun Santri',
            ImportRun::JOB_ACCOUNT_GENERATE_GUARDIANS => 'Generate Akun Wali',
            default => $type === ImportRun::TYPE_BULK ? 'Proses Bulk' : 'Proses Import',
        };
    }

    private function canRetry(ImportRun $run): bool
    {
        return $run->status === ImportRun::STATUS_FAILED
            && in_array($run->type, [
                ImportRun::TYPE_STUDENTS,
                ImportRun::TYPE_TEACHERS,
                ImportRun::TYPE_ENROLLMENTS,
                ImportRun::TYPE_BULK,
            ], true);
    }

    private function requiresSourceFile(ImportRun $run): bool
    {
        return in_array($run->type, [
            ImportRun::TYPE_STUDENTS,
            ImportRun::TYPE_TEACHERS,
            ImportRun::TYPE_ENROLLMENTS,
        ], true);
    }
}
