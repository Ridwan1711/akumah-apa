<?php

namespace App\Logging;

use App\Logging\Handlers\DatabaseLogHandler;
use Monolog\Logger;

class DatabaseLoggerFactory
{
    public function __invoke(array $config): Logger
    {
        $level = Logger::toMonologLevel((string) ($config['level'] ?? 'debug'));
        $logger = new Logger('database');
        $logger->pushHandler(new DatabaseLogHandler($level, true));

        return $logger;
    }
}
