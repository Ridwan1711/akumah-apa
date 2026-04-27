<?php

namespace App\Http\Controllers\Wali;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
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
            ->whereIn('student_id', $studentIds)
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at');

        return Inertia::render('wali/invoices', [
            'invoices' => $query->paginate(15)->withQueryString(),
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
            'paymentType:id,name,code',
            'academicYear:id,name',
            'payments' => fn ($q) => $q->orderByDesc('payment_date'),
        ]);

        $invoice->total_paid = $invoice->totalPaid();
        $invoice->remaining = $invoice->remainingAmount();

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
        $guardian = $request->user()->guardian;
        abort_unless($guardian, 404, 'Data wali tidak ditemukan.');

        return $guardian->students()->pluck('students.id')->toArray();
    }
}
