<?php

namespace App\Events\Schedule;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CellUnlocked implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $scheduleSetId,
        public array $slot,
        public array $by,
    ) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel("schedule.set.{$this->scheduleSetId}")];
    }

    public function broadcastAs(): string
    {
        return 'cell.unlocked';
    }

    public function broadcastWith(): array
    {
        return [
            'slot' => $this->slot,
            'by' => $this->by,
        ];
    }
}

