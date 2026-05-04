<?php

namespace App\Console\Commands;

use App\Models\TeacherLocationLog;
use Illuminate\Console\Command;

class PruneTeacherLocationLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'location:prune-teacher-logs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete expired teacher location logs';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $retentionDays = (int) config('geo_attendance.location_log.retention_days', 60);
        $cutoff = now()->subDays($retentionDays);

        $deleted = TeacherLocationLog::query()
            ->where('recorded_at', '<', $cutoff)
            ->delete();

        $this->info("Deleted {$deleted} location logs older than {$retentionDays} days.");

        return self::SUCCESS;
    }
}
