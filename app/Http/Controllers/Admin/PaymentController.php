<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Notifications\PaymentVerifiedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Payment::with([
            'invoice:id,invoice_number,student_id,payment_type_id,final_amount',
            'invoice.student:id,nis,full_name',
            'invoice.paymentType:id,name,code',
            'verifier:id,name',
        ])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->payment_method, fn ($q, $m) => $q->where('payment_method', $m))
            ->when($request->search, fn ($q, $s) => $q->whereHas('invoice.student', fn ($sq) => $sq->where('full_name', 'ilike', "%{$s}%")->orWhere('nis', 'like', "%{$s}%")))
            ->orderByDesc('created_at');

        return Inertia::render('admin/payments/index', [
            'payments' => $query->paginate(20)->withQueryString(),
            'filters' => $request->only(['status', 'payment_method', 'search']),
            'pendingCount' => Payment::where('status', Payment::STATUS_PENDING)->count(),
            'paymentTypes' => PaymentType::query()->orderBy('category')->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request): Response
    {
        $invoiceId = $request->invoice_id;
        $invoice = $invoiceId
            ? Invoice::with(['student:id,nis,full_name', 'paymentType:id,name,code'])->find($invoiceId)
            : null;

        $unpaidInvoices = Invoice::whereIn('status', [Invoice::STATUS_PENDING, Invoice::STATUS_PARTIAL, Invoice::STATUS_OVERDUE])
            ->with(['student:id,nis,full_name', 'paymentType:id,name'])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return Inertia::render('admin/payments/create', [
            'selectedInvoice' => $invoice,
            'unpaidInvoices' => $unpaidInvoices,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'invoice_id' => ['required', 'exists:invoices,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'payment_method' => ['required', Rule::in([Payment::METHOD_CASH, Payment::METHOD_BANK_TRANSFER])],
            'payment_date' => ['required', 'date'],
            'proof_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
            'notes' => ['nullable', 'string'],
        ]);

        $invoice = Invoice::findOrFail($validated['invoice_id']);
        if (in_array($invoice->status, [Invoice::STATUS_PAID, Invoice::STATUS_CANCELLED])) {
            return redirect()->back()->with('error', 'Tagihan ini sudah lunas atau dibatalkan.');
        }

        $proofPath = null;
        if ($request->hasFile('proof_file')) {
            $proofPath = $request->file('proof_file')->store('payment-proofs', 'public');
        }

        $payment = Payment::create([
            'payment_number' => Payment::generateNumber(),
            'invoice_id' => $validated['invoice_id'],
            'amount' => $validated['amount'],
            'payment_method' => $validated['payment_method'],
            'payment_date' => $validated['payment_date'],
            'proof_file' => $proofPath,
            'status' => Payment::STATUS_VERIFIED,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
            'notes' => $validated['notes'] ?? null,
        ]);

        $invoice->recalculateStatus();

        $student = $invoice->student;
        if ($student) {
            $student->guardians()->whereHas('user')->with('user')->each(function ($guardian) use ($payment) {
                $guardian->user?->notify(new PaymentVerifiedNotification($payment));
            });
        }

        return redirect()->route('admin.payments.index')
            ->with('success', "Pembayaran {$payment->payment_number} berhasil dicatat.");
    }

    public function verify(Request $request, Payment $payment): RedirectResponse
    {
        if ($payment->status !== Payment::STATUS_PENDING) {
            return redirect()->back()->with('error', 'Pembayaran ini sudah diverifikasi atau ditolak.');
        }

        $payment->update([
            'status' => Payment::STATUS_VERIFIED,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
        ]);

        $payment->invoice->recalculateStatus();

        $student = $payment->invoice->student;
        if ($student) {
            $student->guardians()->whereHas('user')->with('user')->each(function ($guardian) use ($payment) {
                $guardian->user?->notify(new PaymentVerifiedNotification($payment));
            });
        }

        return redirect()->back()->with('success', 'Pembayaran berhasil diverifikasi.');
    }

    public function reject(Request $request, Payment $payment): RedirectResponse
    {
        if ($payment->status !== Payment::STATUS_PENDING) {
            return redirect()->back()->with('error', 'Pembayaran ini sudah diverifikasi atau ditolak.');
        }

        $request->validate(['notes' => ['nullable', 'string']]);

        $payment->update([
            'status' => Payment::STATUS_REJECTED,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
            'notes' => $request->notes ?? $payment->notes,
        ]);

        $payment->invoice->recalculateStatus();

        return redirect()->back()->with('success', 'Pembayaran berhasil ditolak.');
    }
}
