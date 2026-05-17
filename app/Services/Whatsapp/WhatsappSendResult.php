<?php

namespace App\Services\Whatsapp;

final class WhatsappSendResult
{
    public function __construct(
        public bool $success,
        public ?string $messageId = null,
    ) {}
}
