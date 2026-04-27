<?php

namespace App\Notifications;

use App\Models\ImportRun;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class BulkRunFinishedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ImportRun $run
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        $statusLabel = $this->run->status === ImportRun::STATUS_COMPLETED ? 'Selesai' : 'Gagal';

        return [
            'type' => 'bulk_run_finished',
            'title' => "Job {$statusLabel}: {$this->humanJobType($this->run->job_type)}",
            'body' => $this->buildMessage(),
            'message' => $this->buildMessage(),
            'url' => $this->resolveUrl(),
            'entity_type' => 'import_run',
            'entity_id' => (string) $this->run->id,
            'role_target' => 'multi',
            'priority' => 'p0',
            'collapse_key' => 'bulk_run_finished_'.(string) $this->run->id,
            'sent_at' => now()->toIso8601String(),
            'notification_id' => (string) $this->id,
        ];
    }

    /**
     * @return array{title: string, body: string, data: array<string, string>}
     */
    public function toFcm(object $notifiable): array
    {
        $title = 'Job '.($this->run->status === ImportRun::STATUS_COMPLETED ? 'Selesai' : 'Gagal').': '.$this->humanJobType($this->run->job_type);
        $body = $this->buildMessage();
        $url = $this->resolveUrl();

        return [
            'title' => $title,
            'body' => $body,
            'data' => [
                'type' => 'bulk_run_finished',
                'title' => $title,
                'body' => $body,
                'url' => $url,
                'entity_type' => 'import_run',
                'entity_id' => (string) $this->run->id,
                'role_target' => 'multi',
                'priority' => 'p0',
                'collapse_key' => 'bulk_run_finished_'.(string) $this->run->id,
                'sent_at' => now()->toIso8601String(),
                'notification_id' => (string) $this->id,
            ],
        ];
    }

    protected function buildMessage(): string
    {
        $stats = "Processed {$this->run->processed_rows}/{$this->run->total_rows}, ".
            "C:{$this->run->created_count}, U:{$this->run->updated_count}, ".
            "S:{$this->run->skipped_count}, F:{$this->run->failed_count}.";

        if ($this->run->status === ImportRun::STATUS_FAILED && $this->run->error_message) {
            return $stats.' Error: '.$this->run->error_message;
        }

        return $stats;
    }

    protected function resolveUrl(): string
    {
        return match ($this->run->job_type) {
            ImportRun::JOB_INVOICE_BULK_GENERATE => '/admin/invoices/generate',
            ImportRun::JOB_CLASS_PROMOTION => '/admin/class-promotion',
            ImportRun::JOB_ACCOUNT_GENERATE_STUDENTS,
            ImportRun::JOB_ACCOUNT_GENERATE_GUARDIANS => '/admin/account-generator',
            default => '/dashboard',
        };
    }

    protected function humanJobType(?string $jobType): string
    {
        return match ($jobType) {
            ImportRun::JOB_INVOICE_BULK_GENERATE => 'Bulk Generate Invoice',
            ImportRun::JOB_CLASS_PROMOTION => 'Class Promotion',
            ImportRun::JOB_ACCOUNT_GENERATE_STUDENTS => 'Generate Akun Santri',
            ImportRun::JOB_ACCOUNT_GENERATE_GUARDIANS => 'Generate Akun Wali',
            ImportRun::JOB_STUDENT_IMPORT => 'Import Santri',
            ImportRun::JOB_TEACHER_IMPORT => 'Import Guru',
            default => 'Bulk Job',
        };
    }
}
