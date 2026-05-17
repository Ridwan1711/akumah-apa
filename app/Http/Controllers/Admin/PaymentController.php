<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Services\Finance\FinanceWhatsappNotificationService;
use App\Services\Finance\InstallmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Payment::with([
            'invoice' => fn ($invoiceQuery) => $invoiceQuery
                ->select(['id', 'invoice_number', 'student_id', 'payment_type_id', 'final_amount'])
                ->withSum([
                    'payments as verified_paid_amount' => fn ($paymentQuery) => $paymentQuery->where('status', Payment::STATUS_VERIFIED),
                ], 'amount'),
            'invoice.student:id,nis,full_name',
            'invoice.paymentType:id,name,code',
            'verifier:id,name',
        ])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->payment_method, fn ($q, $m) => $q->where('payment_method', $m))
            ->when($request->payment_kind === 'full', fn ($q) => $q->whereHas('invoice', fn ($invoiceQuery) => $invoiceQuery->whereColumn('payments.amount', '>=', 'invoices.final_amount')))
            ->when($request->payment_kind === 'partial', fn ($q) => $q->whereHas('invoice', fn ($invoiceQuery) => $invoiceQuery->whereColumn('payments.amount', '<', 'invoices.final_amount')))
            ->when($request->search, fn ($q, $s) => $q->whereHas('invoice.student', fn ($sq) => $sq->where('full_name', 'ilike', "%{$s}%")->orWhere('nis', 'like', "%{$s}%")))
            ->orderByDesc('created_at');

        $payments = $query->paginate(20)->withQueryString();
        $payments->getCollection()->transform(function (Payment $payment): Payment {
            $invoice = $payment->invoice;
            if ($invoice) {
                $invoice->total_paid = (float) ($invoice->verified_paid_amount ?? 0);
                $invoice->remaining = max(0, (float) $invoice->final_amount - $invoice->total_paid);
            }

            return $payment;
        });

        return Inertia::render('admin/payments/index', [
            'payments' => $payments,
            'filters' => $request->only(['status', 'payment_method', 'payment_kind', 'search']),
            'pendingCount' => Payment::where('status', Payment::STATUS_PENDING)->count(),
            'paymentTypes' => PaymentType::query()->orderBy('category')->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request): Response
    {
        $invoiceId = $request->invoice_id;
        $invoice = $invoiceId
            ? Invoice::with(['student:id,nis,full_name', 'paymentType:id,name,code'])
                ->withSum([
                    'payments as verified_paid_amount' => fn ($paymentQuery) => $paymentQuery->where('status', Payment::STATUS_VERIFIED),
                ], 'amount')
                ->withSum([
                    'payments as pending_paid_amount' => fn ($paymentQuery) => $paymentQuery->where('status', Payment::STATUS_PENDING),
                ], 'amount')
                ->find($invoiceId)
            : null;
        if ($invoice) {
            $invoice->total_paid = (float) ($invoice->verified_paid_amount ?? 0);
            $invoice->pending_amount = (float) ($invoice->pending_paid_amount ?? 0);
            $invoice->remaining = max(0, (float) $invoice->final_amount - $invoice->total_paid);
        }

        $unpaidInvoices = Invoice::whereIn('status', [Invoice::STATUS_PENDING, Invoice::STATUS_PARTIAL, Invoice::STATUS_OVERDUE])
            ->with(['student:id,nis,full_name', 'paymentType:id,name'])
            ->withSum([
                'payments as verified_paid_amount' => fn ($paymentQuery) => $paymentQuery->where('status', Payment::STATUS_VERIFIED),
            ], 'amount')
            ->with([
                'payments' => fn ($paymentQuery) => $paymentQuery
                    ->orderByDesc('payment_date')
                    ->limit(8)
                    ->with('verifier:id,name'),
            ])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();
        $unpaidInvoices->each(function (Invoice $invoice): void {
            $invoice->total_paid = (float) ($invoice->verified_paid_amount ?? 0);
            $invoice->remaining = max(0, (float) $invoice->final_amount - $invoice->total_paid);
        });

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
        app(InstallmentService::class)->validateAmount($invoice, (float) $validated['amount']);

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

        app(FinanceWhatsappNotificationService::class)->notifyPaymentVerified($payment);

        return redirect()->route('admin.payments.index')
            ->with('success', "Pembayaran {$payment->payment_number} berhasil dicatat.");
    }

    public function verify(Request $request, Payment $payment): RedirectResponse
    {
        $validated = $request->validate([
            'verified_amount' => ['nullable', 'numeric', 'min:1'],
        ]);

        if ($payment->status !== Payment::STATUS_PENDING) {
            return redirect()->back()->with('error', 'Pembayaran ini sudah diverifikasi atau ditolak.');
        }

        $verifiedAmount = isset($validated['verified_amount'])
            ? (float) $validated['verified_amount']
            : (float) $payment->amount;
        app(InstallmentService::class)->validateAmount($payment->invoice, $verifiedAmount, true);

        $payment->update([
            'amount' => $verifiedAmount,
            'status' => Payment::STATUS_VERIFIED,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
        ]);

        $payment->invoice->recalculateStatus();

        app(FinanceWhatsappNotificationService::class)->notifyPaymentVerified($payment);

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
