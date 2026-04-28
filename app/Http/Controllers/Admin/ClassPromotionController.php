<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Diniyyah\SchoolClass;
use App\Models\ImportRun;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Jobs\ProcessBulkRun;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ClassPromotionController extends Controller
{
    public function index(Request $request): Response
    {
        $perPageOptions = [25, 50, 75, 100, 1000];
        $perPage = (int) $request->input('per_page', 25);
        if (! in_array($perPage, $perPageOptions, true)) {
            $perPage = 25;
        }

        $students = null;

        if ($request->source_class_id) {
            $students = Student::where('current_class_id', $request->source_class_id)
                ->where('status', Student::STATUS_ACTIVE)
                ->orderBy('full_name')
                ->paginate($perPage, ['id', 'nis', 'full_name', 'current_class_id'])
                ->withQueryString();
        }

        $bulkRunsQuery = ImportRun::query()
            ->with('requestedBy:id,name')
            ->where('job_type', ImportRun::JOB_CLASS_PROMOTION)
            ->when($request->run_uploader_id, fn ($q, $id) => $q->where('requested_by', $id))
            ->latest('id');

        $uploaderIds = ImportRun::query()
            ->where('job_type', ImportRun::JOB_CLASS_PROMOTION)
            ->whereNotNull('requested_by')
            ->distinct()
            ->pluck('requested_by');

        return Inertia::render('admin/class-promotion/index', [
            'classes' => SchoolClass::query()->orderBy('order')->orderBy('name')->get(['id', 'name', 'grade_level_id']),
            'students' => $students,
            'filters' => $request->only(['source_class_id', 'run_uploader_id', 'per_page']),
            'bulkRuns' => $bulkRunsQuery->limit(20)->get(),
            'bulkUploaders' => User::query()->whereIn('id', $uploaderIds)->orderBy('name')->get(['id', 'name']),
            'perPageOptions' => $perPageOptions,
        ]);
    }

    public function promote(Request $request): RedirectResponse
    {
        $request->validate([
            'source_class_id' => ['required', 'exists:classes,id'],
            'promotions' => ['required', 'array'],
            'promotions.*.student_id' => ['required', 'exists:students,id'],
            'promotions.*.action' => ['required', 'in:promote,stay,graduate'],
            'promotions.*.target_class_id' => ['nullable', 'exists:classes,id'],
        ]);

        $run = ImportRun::query()->create([
            'uuid' => (string) Str::uuid(),
            'type' => ImportRun::TYPE_BULK,
            'job_type' => ImportRun::JOB_CLASS_PROMOTION,
            'status' => ImportRun::STATUS_QUEUED,
            'requested_by' => $request->user()?->id,
            'file_name' => 'class-promotion',
            'file_path' => '-',
            'meta' => [
                'source_class_id' => $request->source_class_id,
                'period_id' => $request->input('period_id'),
                'promotions' => $request->promotions,
            ],
        ]);

        ProcessBulkRun::dispatch($run->id);

        return redirect()->back()->with('success', 'Proses kenaikan kelas dipindahkan ke background queue.');
    }

    public function retryBulkRun(ImportRun $importRun): RedirectResponse
    {
        abort_unless($importRun->job_type === ImportRun::JOB_CLASS_PROMOTION, 404);
        abort_unless($importRun->status === ImportRun::STATUS_FAILED, 422, 'Hanya job gagal yang bisa di-retry.');

        $retryRun = ImportRun::query()->create([
            'uuid' => (string) Str::uuid(),
            'type' => ImportRun::TYPE_BULK,
            'job_type' => ImportRun::JOB_CLASS_PROMOTION,
            'status' => ImportRun::STATUS_QUEUED,
            'requested_by' => request()->user()?->id,
            'file_name' => $importRun->file_name,
            'file_path' => '-',
            'meta' => $importRun->meta,
        ]);

        ProcessBulkRun::dispatch($retryRun->id);

        return redirect()->back()->with('success', 'Retry kenaikan kelas diproses di background.');
    }
}
