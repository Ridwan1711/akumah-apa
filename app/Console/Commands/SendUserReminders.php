<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Models\User;
use App\Notifications\PeriodicReminderNotification;
use App\Notifications\ProfileIncompleteReminderNotification;
use Illuminate\Console\Command;

class SendUserReminders extends Command
{
    protected $signature = 'notifications:send-reminders {--periodic : Kirim juga periodic reminder}';

    protected $description = 'Kirim reminder profile incomplete dan periodic reminder ke user aplikasi.';

    public function handle(): int
    {
        $profileCount = $this->sendProfileIncompleteReminders();
        $this->info("Profile incomplete reminder terkirim: {$profileCount}");

        $periodicCount = 0;
        $shouldSendPeriodic = (bool) $this->option('periodic') || now()->isMonday();
        if ($shouldSendPeriodic) {
            $periodicCount = $this->sendPeriodicReminders();
            $this->info("Periodic reminder terkirim: {$periodicCount}");
        } else {
            $this->info('Periodic reminder dilewati (hanya kirim hari Senin atau pakai --periodic).');
        }

        return self::SUCCESS;
    }

    private function sendProfileIncompleteReminders(): int
    {
        $students = Student::query()
            ->where('status', Student::STATUS_ACTIVE)
            ->where(function ($q) {
                $q->whereNull('nik')
                    ->orWhereNull('birth_date')
                    ->orWhereNull('address');
            })
            ->whereNotNull('user_id')
            ->with('user')
            ->get();

        $count = 0;
        foreach ($students as $student) {
            if (! $student->user) {
                continue;
            }
            /** @var User $user */
            $user = $student->user;
            $user->notify(new ProfileIncompleteReminderNotification());
            $count++;
        }

        return $count;
    }

    private function sendPeriodicReminders(): int
    {
        $users = User::query()
            ->where('is_active', true)
            ->get();

        $count = 0;
        foreach ($users as $user) {
            /** @var User $user */
            $user->notify(new PeriodicReminderNotification());
            $count++;
        }

        return $count;
    }
}
