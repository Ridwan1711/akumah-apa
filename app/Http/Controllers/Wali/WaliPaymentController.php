<?php

namespace App\Http\Controllers\Wali;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Finance\InstallmentService;
use App\Notifications\PaymentPendingNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WaliPaymentController extends Controller
{
    public function invoices(Request $request): Response
    {
        $studentIds = $this->getChildStudentIds($request);

        $query = Invoice::with([
            'student:id,nis,full_name',
            'paymentType:id,name,code,category',
            'academicYear:id,name',
        ])
            ->withCount('payments')
            ->withSum([
                'payments as verified_paid_amount' => fn ($paymentQuery) => $paymentQuery->where('status', Payment::STATUS_VERIFIED),
            ], 'amount')
            ->withSum([
                'payments as pending_paid_amount' => fn ($paymentQuery) => $paymentQuery->where('status', Payment::STATUS_PENDING),
            ], 'amount')
            ->whereIn('student_id', $studentIds)
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at');

        $invoices = $query->paginate(15)->withQueryString();
        $invoices->getCollection()->transform(function (Invoice $invoice): Invoice {
            $invoice->total_paid = (float) ($invoice->verified_paid_amount ?? 0);
            $invoice->pending_amount = (float) ($invoice->pending_paid_amount ?? 0);
            $invoice->remaining = max(0, (float) $invoice->final_amount - $invoice->total_paid);

            return $invoice;
        });

        return Inertia::render('wali/invoices', [
            'invoices' => $invoices,
            'filters' => $request->only(['status']),
            'midtransClientKey' => config('midtrans.client_key'),
        ]);
    }

    public function invoiceDetail(Request $request, Invoice $invoice): Response
    {
        $studentIds = $this->getChildStudentIds($request);
        abort_unless(in_array($invoice->student_id, $studentIds), 403);

        $invoice->load([
            'student:id,nis,full_name',
            'paymentType:id,name,code,default_breakdown',
            'academicYear:id,name',
            'payments' => fn ($q) => $q->orderByDesc('payment_date'),
        ]);

        $invoice->total_paid = $invoice->totalPaid();
        $invoice->pending_amount = $invoice->pendingAmount();
        $invoice->remaining = $invoice->remainingAmount();
        $invoice->breakdown_items = $invoice->resolvedBreakdown();

        return Inertia::render('wali/invoice-detail', [
            'invoice' => $invoice,
            'midtransClientKey' => config('midtrans.client_key'),
        ]);
    }

    public function uploadProof(Request $request, Invoice $invoice): RedirectResponse
    {
        $studentIds = $this->getChildStudentIds($request);
        abort_unless(in_array($invoice->student_id, $studentIds), 403);

        $request->validate([
            'proof_file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
            'amount' => ['required', 'numeric', 'min:1'],
            'notes' => ['nullable', 'string'],
        ]);
        app(InstallmentService::class)->validateAmount($invoice, (float) $request->amount);

        $proofPath = $request->file('proof_file')->store('payment-proofs', 'public');

        $payment = Payment::create([
            'payment_number' => Payment::generateNumber(),
            'invoice_id' => $invoice->id,
            'amount' => $request->amount,
            'payment_method' => Payment::METHOD_BANK_TRANSFER,
            'payment_date' => now()->toDateString(),
            'proof_file' => $proofPath,
            'status' => Payment::STATUS_PENDING,
            'notes' => $request->notes,
        ]);

        \App\Models\User::whereHas('roles', fn ($q) => $q->whereIn('name', ['super_admin', 'admin_keuangan']))
            ->where('is_active', true)
            ->each(fn ($user) => $user->notify(new PaymentPendingNotification($payment)));

        return redirect()->back()->with('success', 'Bukti pembayaran berhasil diupload. Menunggu verifikasi admin.');
    }

    public function paymentHistory(Request $request): Response
    {
        $studentIds = $this->getChildStudentIds($request);

        $payments = Payment::whereHas('invoice', fn ($q) => $q->whereIn('student_id', $studentIds))
            ->with([
                'invoice:id,invoice_number,student_id,payment_type_id,final_amount',
                'invoice.student:id,full_name',
                'invoice.paymentType:id,name',
            ])
            ->orderByDesc('payment_date')
            ->paginate(15);

        return Inertia::render('wali/payment-history', [
            'payments' => $payments,
        ]);
    }

    public function createPayment(Request $request, Invoice $invoice): RedirectResponse
    {
        $studentIds = $this->getChildStudentIds($request);
        abort_unless(in_array($invoice->student_id, $studentIds), 403);

        return redirect()->route('wali.invoices.show', $invoice);
    }

    private function getChildStudentIds(Request $request): array
    {
        $guardian = $request->user()->primaryGuardian();
        abort_unless($guardian !== null, 404, 'Data wali tidak ditemukan.');

        return $guardian->students()->pluck('students.id')->toArray();
    }
}
