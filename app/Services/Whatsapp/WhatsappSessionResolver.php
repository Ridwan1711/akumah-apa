<?php

namespace App\Services\Whatsapp;

final class WhatsappSessionResolver
{
    public function resolveForTag(?string $tag): string
    {
        $routes = (array) config('services.wa.session_routes', []);
        $default = (string) ($routes['default'] ?? 'pesantren');

        if ($tag === null || $tag === '') {
            return $default;
        }

        return (string) ($routes[$tag] ?? $default);
    }
}
