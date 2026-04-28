<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PaymentTypeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/payment-types/index', [
            'paymentTypes' => PaymentType::orderBy('category')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:payment_types,code'],
            'category' => ['required', Rule::in(PaymentType::CATEGORIES)],
            'is_recurring' => ['boolean'],
            'default_amount' => ['required', 'numeric', 'min:0'],
            'kuliah_amount' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        PaymentType::create($validated);

        return redirect()->route('admin.payment-types.index')
            ->with('success', 'Jenis pembayaran berhasil ditambahkan.');
    }

    public function update(Request $request, PaymentType $paymentType): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('payment_types', 'code')->ignore($paymentType->id)],
            'category' => ['required', Rule::in(PaymentType::CATEGORIES)],
            'is_recurring' => ['boolean'],
            'default_amount' => ['required', 'numeric', 'min:0'],
            'kuliah_amount' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $paymentType->update($validated);

        return redirect()->route('admin.payment-types.index')
            ->with('success', 'Jenis pembayaran berhasil diperbarui.');
    }

    public function destroy(PaymentType $paymentType): RedirectResponse
    {
        if ($paymentType->invoices()->exists()) {
            return redirect()->back()->with('error', 'Tidak bisa menghapus jenis pembayaran yang sudah memiliki tagihan.');
        }

        $paymentType->delete();

        return redirect()->route('admin.payment-types.index')
            ->with('success', 'Jenis pembayaran berhasil dihapus.');
    }
}
