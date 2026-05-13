<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportRun extends Model
{
    use HasFactory;

    public const TYPE_STUDENTS = 'students';

    public const TYPE_TEACHERS = 'teachers';

    public const TYPE_ENROLLMENTS = 'enrollments';

    public const TYPE_BULK = 'bulk';

    public const TYPE_INVOICES = 'invoices';

    public const JOB_STUDENT_IMPORT = 'student_import';

    public const JOB_TEACHER_IMPORT = 'teacher_import';

    public const JOB_ENROLLMENT_IMPORT = 'enrollment_import';

    public const JOB_INVOICE_IMPORT = 'invoice_import';

    public const JOB_INVOICE_BULK_GENERATE = 'invoice_bulk_generate';

    public const JOB_CLASS_PROMOTION = 'class_promotion';

    public const JOB_ACCOUNT_GENERATE_STUDENTS = 'account_generate_students';

    public const JOB_ACCOUNT_GENERATE_GUARDIANS = 'account_generate_guardians';

    /** Maks santri per job generate akun (kurangi risiko `result_payload` JSON terpotong; ±300 akun jika +wali). */
    public const ACCOUNT_BATCH_MAX_STUDENTS = 150;

    /** Maks wali per job generate akun wali. */
    public const ACCOUNT_BATCH_MAX_GUARDIANS = 150;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const FINAL_STATUSES = [
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'uuid',
        'type',
        'job_type',
        'strategy',
        'status',
        'requested_by',
        'file_name',
        'file_path',
        'total_rows',
        'processed_rows',
        'created_count',
        'updated_count',
        'skipped_count',
        'failed_count',
        'error_report_path',
        'error_message',
        'started_at',
        'finished_at',
        'meta',
        'result_payload',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'result_payload' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isFinal(): bool
    {
        return in_array($this->status, self::FINAL_STATUSES, true);
    }
}
