<?php

namespace App\Notifications\Messages;

final class WhatsappMessage
{
    public function __construct(
        public string $text,
        public ?string $overridePhone = null,
        public ?string $tag = null,
        public ?string $sessionSlug = null,
    ) {}

    public static function make(
        string $text,
        ?string $overridePhone = null,
        ?string $tag = null,
        ?string $sessionSlug = null,
    ): self {
        return new self($text, $overridePhone, $tag, $sessionSlug);
    }
}
