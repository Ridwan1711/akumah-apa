<?php

namespace App\Events\Schedule;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CellsRestored implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $scheduleSetId,
        public int $restored,
        public int $skipped,
        public array $by,
    ) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel("schedule.set.{$this->scheduleSetId}")];
    }

    public function broadcastAs(): string
    {
        return 'cells.restored';
    }

    public function broadcastWith(): array
    {
        return [
            'restored' => $this->restored,
            'skipped' => $this->skipped,
            'by' => $this->by,
        ];
    }
}

