<?php

namespace App\Listeners\Schedule;

use App\Services\Schedule\ScheduleLockService;
use Laravel\Reverb\Events\ConnectionPruned;

class ReleaseLocksOnPresenceLeave
{
    public function __construct(private ScheduleLockService $locks) {}

    public function handle(ConnectionPruned $event): void
    {
        $userId = (int) ($event->connection->data('user_id') ?? 0);
        if ($userId <= 0) {
            return;
        }

        $this->locks->releaseAllForUserAcrossSets($userId);
    }
}

