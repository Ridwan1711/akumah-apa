<?php

namespace App\Console\Commands;

use App\Models\Student;
use Illuminate\Console\Command;

class BackfillUserOfficialPhotos extends Command
{
    protected $signature = 'users:backfill-official-photos {--dry-run : Preview without saving}';

    protected $description = 'Backfill users.official_photo_path from legacy students.photo';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $count = 0;

        Student::query()
            ->whereNotNull('photo')
            ->where('photo', '!=', '')
            ->with('user')
            ->chunkById(200, function ($students) use ($dryRun, &$count) {
                foreach ($students as $student) {
                    if (! $student->user) {
                        continue;
                    }
                    if (! empty($student->user->official_photo_path)) {
                        continue;
                    }

                    $count++;
                    if (! $dryRun) {
                        $student->user->forceFill([
                            'official_photo_path' => $student->photo,
                        ])->save();
                    }
                }
            });

        $this->info(($dryRun ? 'Preview' : 'Updated')." rows: {$count}");

        return self::SUCCESS;
    }
}

