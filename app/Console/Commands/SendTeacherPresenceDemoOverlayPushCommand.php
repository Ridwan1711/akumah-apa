<?php

namespace App\Console\Commands;

use App\Actions\TeacherPresence\SendTeacherPresenceOverlayDemoPush;
use App\Models\User;
use Illuminate\Console\Command;
use InvalidArgumentException;

class SendTeacherPresenceDemoOverlayPushCommand extends Command
{
    protected $signature = 'teacher-presence:demo-overlay-push
                            {user : ID user atau email guru}
                            {--session= : Opsional: lesson_sessions.id (harus jadwal guru tersebut)}';

    protected $description = 'Kirim push FCM "konfirmasi kehadiran guru" untuk memicu overlay di app (demo Play Store / QA).';

    public function handle(SendTeacherPresenceOverlayDemoPush $action): int
    {
        $userArg = (string) $this->argument('user');
        $sessionOpt = $this->option('session');

        $teacher = is_numeric($userArg)
            ? User::query()->findOrFail((int) $userArg)
            : User::query()->where('email', $userArg)->firstOrFail();

        $sessionId = $sessionOpt !== null && $sessionOpt !== ''
            ? (int) $sessionOpt
            : null;

        try {
            $session = $action->execute($teacher, $sessionId);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Push terkirim ke {$teacher->name} (ID {$teacher->id}), sesi #{$session->id}.");
        $this->warn('Di Android overlay bisa muncul di background jika izin "tampil di atas aplikasi lain" sudah diberikan.');

        return self::SUCCESS;
    }
}
