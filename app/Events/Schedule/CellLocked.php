<?php

namespace App\Events\Schedule;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CellLocked implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $scheduleSetId,
        public array $lock,
        public array $by,
    ) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel("schedule.set.{$this->scheduleSetId}")];
    }

    public function broadcastAs(): string
    {
        return 'cell.locked';
    }

    public function broadcastWith(): array
    {
        return [
            'lock' => $this->lock,
            'by' => $this->by,
        ];
    }
}

