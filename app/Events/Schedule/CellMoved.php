<?php

namespace App\Events\Schedule;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CellMoved implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $scheduleSetId,
        public string $mode,
        public ?array $sourceOld,
        public ?array $sourceNew,
        public ?array $targetOld,
        public ?array $targetNew,
        public array $by,
    ) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel("schedule.set.{$this->scheduleSetId}")];
    }

    public function broadcastAs(): string
    {
        return 'cell.moved';
    }

    public function broadcastWith(): array
    {
        return [
            'mode' => $this->mode,
            'source_old' => $this->sourceOld,
            'source_new' => $this->sourceNew,
            'target_old' => $this->targetOld,
            'target_new' => $this->targetNew,
            'by' => $this->by,
        ];
    }
}

