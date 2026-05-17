<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Notifications\Messages\WhatsappMessage;
use App\Services\Finance\FinanceWhatsappPhone;
use App\Services\Whatsapp\Exceptions\WhatsappHaltedException;
use App\Services\Whatsapp\Exceptions\WhatsappNotReadyException;
use App\Services\Whatsapp\Exceptions\WhatsappRateLimitedException;
use App\Services\Whatsapp\WhatsappClient;
use App\Services\Whatsapp\WhatsappSessionResolver;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsappChannel
{
    public function __construct(
        private readonly WhatsappClient $client,
        private readonly WhatsappSessionResolver $sessionResolver,
    ) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! config('services.wa.enabled')) {
            return;
        }

        if (! method_exists($notification, 'toWhatsapp')) {
            return;
        }

        $payload = $notification->toWhatsapp($notifiable);
        if ($payload === null) {
            return;
        }

        $message = $payload instanceof WhatsappMessage
            ? $payload
            : WhatsappMessage::make((string) $payload);

        $text = trim($message->text);
        if ($text === '') {
            return;
        }

        $phone = $this->resolvePhone($notifiable, $notification, $message);
        if ($phone === null) {
            Log::info('wa_channel_skipped_no_phone', [
                'notification' => class_basename($notification),
                'notifiable' => class_basename($notifiable),
            ]);

            return;
        }

        $tag = $message->tag ?? class_basename($notification);
        $sessionSlug = $message->sessionSlug ?? $this->sessionResolver->resolveForTag($tag);

        try {
            $this->client->send($phone, $text, $tag, true, $sessionSlug);
        } catch (WhatsappRateLimitedException|WhatsappNotReadyException|WhatsappHaltedException $e) {
            Log::info('wa_channel_retryable', [
                'notification' => class_basename($notification),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } catch (Throwable $e) {
            Log::warning('wa_channel_failed', [
                'notification' => class_basename($notification),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function resolvePhone(object $notifiable, Notification $notification, WhatsappMessage $message): ?string
    {
        if ($message->overridePhone !== null && $message->overridePhone !== '') {
            return FinanceWhatsappPhone::normalize($message->overridePhone);
        }

        if (method_exists($notifiable, 'routeNotificationForWhatsapp')) {
            $routed = $notifiable->routeNotificationForWhatsapp($notification);
            if (is_string($routed) && $routed !== '') {
                return FinanceWhatsappPhone::normalize($routed);
            }
        }

        if (method_exists($notifiable, 'routeNotificationFor')) {
            $routed = $notifiable->routeNotificationFor(WhatsappChannel::class, $notification)
                ?? $notifiable->routeNotificationFor('whatsapp', $notification);
            if (is_string($routed) && $routed !== '') {
                return FinanceWhatsappPhone::normalize($routed);
            }
        }

        if ($notifiable instanceof User) {
            if (! $notifiable->whatsapp_notifications_enabled) {
                return null;
            }

            if ($notifiable->whatsapp_phone_verified_at === null) {
                return null;
            }

            return FinanceWhatsappPhone::normalize($notifiable->whatsapp_phone);
        }

        return null;
    }
}
