<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Notifications\AnnouncementSegmentedNotification;
use Illuminate\Console\Command;

class SendSegmentedAnnouncement extends Command
{
    protected $signature = 'notifications:announce
        {--role=multi : Target role (santri|guru|wali|admin|multi)}
        {--title= : Judul pengumuman}
        {--body= : Isi pengumuman}
        {--url=/notifications : Internal route tujuan}';

    protected $description = 'Kirim announcement segmented ke role tertentu (FCM + inbox).';

    public function handle(): int
    {
        $role = strtolower((string) $this->option('role'));
        $title = trim((string) ($this->option('title') ?? 'Pengumuman'));
        $body = trim((string) ($this->option('body') ?? 'Ada pembaruan informasi untuk Anda.'));
        $url = (string) $this->option('url');

        $query = User::query()->where('is_active', true);
        if ($role !== 'multi') {
            $query->whereHas('roles', fn ($q) => $q->whereIn('name', $this->mapRole($role)));
        }

        $sent = 0;
        $query->chunkById(200, function ($users) use (&$sent, $title, $body, $role, $url) {
            foreach ($users as $user) {
                /** @var User $user */
                $user->notify(new AnnouncementSegmentedNotification($title, $body, $role, $url));
                $sent++;
            }
        });

        $this->info("Announcement terkirim ke {$sent} user.");

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function mapRole(string $role): array
    {
        return match ($role) {
            'santri' => [Role::SANTRI],
            'guru' => [Role::GURU],
            'wali' => [Role::WALI_SANTRI],
            'admin' => Role::ADMIN_ROLES,
            default => [],
        };
    }
}
