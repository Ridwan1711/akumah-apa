<?php

namespace App\Services\Whatsapp\Exceptions;

use RuntimeException;

class WhatsappRateLimitedException extends RuntimeException
{
    public function __construct(
        string $message = 'WhatsApp rate limited',
        public readonly int $retryAfterSeconds = 60,
    ) {
        parent::__construct($message);
    }
}
