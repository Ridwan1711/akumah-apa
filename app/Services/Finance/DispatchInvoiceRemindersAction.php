<?php

namespace App\Services\Finance;

use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\User;
use App\Notifications\InvoiceReminderNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

final class DispatchInvoiceRemindersAction
{
    public function __construct(
        private readonly FinanceWhatsappNotificationService $whatsappNotifications,
    ) {}

    /**
     * @param  Collection<int, Invoice>  $invoices
     * @return array{
     *   sent_count:int,
     *   recipients_without_account:int,
     *   wa_queued:int,
     *   failed:array<int, array{invoice_id:int,error:string}>
     * }
     */
    public function run(
        Collection $invoices,
        ?string $customMessage,
        ?int $sentByUserId,
        bool $sendAppNotification,
        bool $sendWhatsapp,
        int $waStaggerStart = 0,
    ): array {
        Log::info('invoice_reminders_dispatch_run', [
            'invoice_count' => $invoices->count(),
            'send_app_notification' => $sendAppNotification,
            'send_whatsapp' => $sendWhatsapp,
            'wa_enabled' => (bool) config('services.wa.enabled'),
            'wa_queue' => (string) config('services.wa.queue', 'wa'),
        ]);

        $sentCount = 0;
        $recipientsWithoutAccount = 0;
        $waQueued = 0;
        $failed = [];
        $waStagger = $waStaggerStart;
        /** @var array<string, true> $waDedupePhones */
        $waDedupePhones = [];

        /** @var Invoice $invoice */
        foreach ($invoices as $invoice) {
            try {
                $invoice->loadMissing('student.user', 'student.guardians.user', 'paymentType');
                $student = $invoice->student;
                $guardianUsers = $student?->guardians?->pluck('user')->filter()->unique('id') ?? collect();
                $guardiansWithoutUser = $student?->guardians?->filter(fn ($g) => ! $g->user) ?? collect();
                $recipientsWithoutAccount += $guardiansWithoutUser->count();

                $invoiceTouched = false;

                foreach ($guardianUsers as $guardianUser) {
                    $notification = new InvoiceReminderNotification(
                        $invoice,
                        $customMessage,
                        $sentByUserId,
                        $sendAppNotification,
                    );

                    if ($sendAppNotification) {
                        $guardianUser->notify($notification);
                        $sentCount++;
                        $invoiceTouched = true;
                    }

                    if ($sendWhatsapp && $student instanceof Student) {
                        if ($this->queueWaForInvoiceReminder(
                            $invoice,
                            $student,
                            $notification,
                            $guardianUser,
                            null,
                            $waDedupePhones,
                            $waStagger,
                        )) {
                            $waQueued++;
                            $waStagger++;
                            $invoiceTouched = true;
                        }
                    }
                }

                foreach ($guardiansWithoutUser as $guardian) {
                    if (! $sendWhatsapp || ! $student instanceof Student) {
                        continue;
                    }

                    $notification = new InvoiceReminderNotification(
                        $invoice,
                        $customMessage,
                        $sentByUserId,
                        $sendAppNotification,
                    );

                    if ($this->queueWaForInvoiceReminder(
                        $invoice,
                        $student,
                        $notification,
                        null,
                        $guardian,
                        $waDedupePhones,
                        $waStagger,
                    )) {
                        $waQueued++;
                        $waStagger++;
                        $invoiceTouched = true;
                    }
                }

                $santriUser = $student?->user;
                if (
                    $santriUser instanceof User
                    && ! $guardianUsers->contains(fn (User $u): bool => (int) $u->id === (int) $santriUser->id)
                ) {
                    $notification = new InvoiceReminderNotification(
                        $invoice,
                        $customMessage,
                        $sentByUserId,
                        $sendAppNotification,
                    );

                    if ($sendAppNotification) {
                        $santriUser->notify($notification);
                        $sentCount++;
                        $invoiceTouched = true;
                    }

                    if ($sendWhatsapp && $student instanceof Student) {
                        if ($this->queueWaForInvoiceReminder(
                            $invoice,
                            $student,
                            $notification,
                            $santriUser,
                            null,
                            $waDedupePhones,
                            $waStagger,
                        )) {
                            $waQueued++;
                            $waStagger++;
                            $invoiceTouched = true;
                        }
                    }
                }

                if ($invoiceTouched) {
                    $invoice->forceFill([
                        'last_reminder_sent_at' => now(),
                        'reminder_count' => ((int) $invoice->reminder_count) + 1,
                    ])->saveQuietly();
                }
            } catch (\Throwable $exception) {
                $failed[] = [
                    'invoice_id' => (int) $invoice->id,
                    'error' => $exception->getMessage(),
                ];
            }
        }

        return [
            'sent_count' => $sentCount,
            'recipients_without_account' => $recipientsWithoutAccount,
            'wa_queued' => $waQueued,
            'failed' => $failed,
        ];
    }

    /**
     * @param  array<string, true>  $waDedupePhones
     */
    private function queueWaForInvoiceReminder(
        Invoice $invoice,
        Student $student,
        InvoiceReminderNotification $notification,
        ?User $waliUser,
        ?Guardian $guardianWithoutUser,
        array &$waDedupePhones,
        int $waStagger,
    ): bool {
        $recipient = app(FinanceWhatsappRecipient::class);
        $phone = $guardianWithoutUser !== null
            ? $recipient->resolve($student, null, $guardianWithoutUser)
            : $recipient->resolve($student, $waliUser);

        if ($phone === null) {
            Log::warning('invoice_wa_skipped_no_phone', [
                'invoice_id' => (int) $invoice->id,
                'student_id' => (int) $student->id,
            ]);

            return false;
        }

        $dedupeKey = $invoice->id.'|'.$phone;
        if (isset($waDedupePhones[$dedupeKey])) {
            return false;
        }

        $waDedupePhones[$dedupeKey] = true;

        return $this->whatsappNotifications->notifyInvoiceReminder(
            $invoice,
            $student,
            $notification,
            $waliUser,
            $guardianWithoutUser,
            $waStagger,
        );
    }
}
