<?php

namespace App\Notifications;

use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ScheduleTomorrowNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $role  guru|santri|wali
     * @param  array<int, array<string, mixed>>  $entries Senarai entri jadwal (subject, class_name, start_time, end_time, ...)
     * @param  string  $dayName  Nama hari besok, mis. "Senin"
     * @param  string  $deeplink  URL tujuan di aplikasi Flutter
     */
    public function __construct(
        public string $role,
        public array $entries,
        public string $dayName,
        public string $deeplink,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'schedule_tomorrow',
            'title' => $this->title(),
            'body' => $this->body(),
            'message' => $this->body(),
            'url' => $this->deeplink,
            'entity_type' => 'schedule',
            'entity_id' => '',
            'role_target' => $this->role,
            'priority' => 'p0',
            'collapse_key' => 'schedule_tomorrow_'.$this->role,
            'sent_at' => now()->toIso8601String(),
            'notification_id' => (string) $this->id,
        ];
    }

    /**
     * @return array{title: string, body: string, data: array<string, string>}
     */
    public function toFcm(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'data' => [
                'type' => 'schedule_tomorrow',
                'title' => $this->title(),
                'body' => $this->body(),
                'url' => $this->deeplink,
                'entity_type' => 'schedule',
                'entity_id' => '',
                'role_target' => $this->role,
                'priority' => 'p0',
                'collapse_key' => 'schedule_tomorrow_'.$this->role,
                'sent_at' => now()->toIso8601String(),
                'notification_id' => (string) $this->id,
            ],
        ];
    }

    private function title(): string
    {
        return 'Pengingat Jadwal Besok';
    }

    private function body(): string
    {
        $count = count($this->entries);
        if ($count === 0) {
            return "Tidak ada jadwal {$this->dayName}.";
        }

        $preview = collect($this->entries)
            ->take(3)
            ->map(function (array $e) {
                $subject = $e['subject_name'] ?? '-';
                $time = $e['start_time'] ?? '';

                return trim($time.' '.$subject);
            })
            ->implode(', ');

        $more = $count > 3 ? ' dll.' : '';

        return "Besok ({$this->dayName}) ada {$count} jadwal: {$preview}{$more}";
    }
}
