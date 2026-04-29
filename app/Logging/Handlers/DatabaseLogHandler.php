<?php

namespace App\Logging\Handlers;

use App\Services\SystemLogService;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;

class DatabaseLogHandler extends AbstractProcessingHandler
{
    protected function write(LogRecord $record): void
    {
        app(SystemLogService::class)->write(
            level: $record->level->getName(),
            message: $record->message,
            context: $record->context,
            extra: $record->extra,
            channel: $record->channel,
            loggedAt: $record->datetime,
        );
    }
}
