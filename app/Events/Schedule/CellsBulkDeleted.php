<?php

namespace App\Events\Schedule;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CellsBulkDeleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $scheduleSetId,
        public array $scope,
        public int $count,
        public array $by,
    ) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel("schedule.set.{$this->scheduleSetId}")];
    }

    public function broadcastAs(): string
    {
        return 'cells.bulk_deleted';
    }

    public function broadcastWith(): array
    {
        return [
            'scope' => $this->scope,
            'count' => $this->count,
            'by' => $this->by,
        ];
    }
}

