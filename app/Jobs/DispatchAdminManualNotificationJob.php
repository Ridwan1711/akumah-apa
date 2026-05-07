<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\AdminManualNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchAdminManualNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    /**
     * @param  array<int, array{user_id:int,device_token_ids:array<int,int>}>  $targets
     */
    public function __construct(
        public string $title,
        public string $body,
        public string $deeplink,
        public array $targets
    ) {}

    public function handle(): void
    {
        foreach (array_chunk($this->targets, 200) as $chunk) {
            $userIds = collect($chunk)->pluck('user_id')->unique()->values()->all();
            $users = User::query()
                ->whereIn('id', $userIds)
                ->where('is_active', true)
                ->get()
                ->keyBy('id');

            foreach ($chunk as $target) {
                /** @var User|null $user */
                $user = $users->get((int) $target['user_id']);
                if (! $user) {
                    continue;
                }

                $user->notify(new AdminManualNotification(
                    titleText: $this->title,
                    bodyText: $this->body,
                    deeplink: $this->deeplink,
                    deviceTokenIds: $target['device_token_ids'],
                ));
            }
        }
    }
}
