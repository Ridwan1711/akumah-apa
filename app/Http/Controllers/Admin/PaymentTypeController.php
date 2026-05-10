<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentType;
use App\Models\PaymentTypeTingkatSekolahRule;
use App\Models\TingkatSekolah;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PaymentTypeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/payment-types/index', [
            'paymentTypes' => PaymentType::with(['tingkatRules.tingkatSekolah'])
                ->orderBy('category')
                ->orderBy('name')
                ->get(),
            'tingkatSekolahs' => TingkatSekolah::query()
                ->orderBy('order')
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
            'default_breakdown' => ['nullable', 'array'],
            'default_breakdown.*.label' => ['required_with:default_breakdown', 'string', 'max:120'],
            'default_breakdown.*.amount' => ['required_with:default_breakdown', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'tingkat_rules' => ['nullable', 'array'],
            'tingkat_rules.*.tingkat_sekolah_id' => ['required', 'integer', 'exists:tingkat_sekolahs,id'],
            'tingkat_rules.*.is_enabled' => ['sometimes', 'boolean'],
            'tingkat_rules.*.amount' => ['nullable', 'numeric', 'min:0'],
            'tingkat_rules.*.breakdown' => ['nullable', 'array'],
            'tingkat_rules.*.breakdown.*.label' => ['required_with:tingkat_rules.*.breakdown', 'string', 'max:120'],
            'tingkat_rules.*.breakdown.*.amount' => ['required_with:tingkat_rules.*.breakdown', 'numeric', 'min:0.01'],
        ]);

        $tingkatRulesPayload = $validated['tingkat_rules'] ?? [];
        unset($validated['tingkat_rules']);

        $validated = $this->normalizePaymentTypePayload($validated);
        $tingkatRows = $this->normalizeTingkatRulesPayload($tingkatRulesPayload);

        $paymentType = PaymentType::create($validated);
        $this->persistTingkatRules($paymentType, $tingkatRows);

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
            'default_breakdown' => ['nullable', 'array'],
            'default_breakdown.*.label' => ['required_with:default_breakdown', 'string', 'max:120'],
            'default_breakdown.*.amount' => ['required_with:default_breakdown', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'tingkat_rules' => ['nullable', 'array'],
            'tingkat_rules.*.tingkat_sekolah_id' => ['required', 'integer', 'exists:tingkat_sekolahs,id'],
            'tingkat_rules.*.is_enabled' => ['sometimes', 'boolean'],
            'tingkat_rules.*.amount' => ['nullable', 'numeric', 'min:0'],
            'tingkat_rules.*.breakdown' => ['nullable', 'array'],
            'tingkat_rules.*.breakdown.*.label' => ['required_with:tingkat_rules.*.breakdown', 'string', 'max:120'],
            'tingkat_rules.*.breakdown.*.amount' => ['required_with:tingkat_rules.*.breakdown', 'numeric', 'min:0.01'],
        ]);

        $tingkatRulesPayload = $validated['tingkat_rules'] ?? [];
        unset($validated['tingkat_rules']);

        $validated = $this->normalizePaymentTypePayload($validated);
        $tingkatRows = $this->normalizeTingkatRulesPayload($tingkatRulesPayload);

        $paymentType->update($validated);
        $this->persistTingkatRules($paymentType, $tingkatRows);

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

    /**
     * @param  array<int, array<string, mixed>>  $rules
     */
    private function persistTingkatRules(PaymentType $paymentType, array $rules): void
    {
        $paymentType->tingkatRules()->delete();

        foreach ($rules as $row) {
            PaymentTypeTingkatSekolahRule::query()->create([
                'payment_type_id' => $paymentType->id,
                'tingkat_sekolah_id' => $row['tingkat_sekolah_id'],
                'is_enabled' => $row['is_enabled'],
                'amount' => $row['amount'],
                'breakdown' => $row['breakdown'],
            ]);
        }
    }

    /**
     * @param  array<int, mixed>  $payload
     * @return array<int, array{tingkat_sekolah_id:int, is_enabled:bool, amount: ?float, breakdown: array<int, array{label:string, amount:float}>}>
     */
    private function normalizeTingkatRulesPayload(array $payload): array
    {
        $seen = [];
        $out = [];

        foreach ($payload as $index => $rule) {
            if (! is_array($rule)) {
                continue;
            }

            $tingkatId = (int) ($rule['tingkat_sekolah_id'] ?? 0);
            if ($tingkatId <= 0) {
                continue;
            }

            if (isset($seen[$tingkatId])) {
                throw ValidationException::withMessages([
                    "tingkat_rules.$index.tingkat_sekolah_id" => 'Tingkat duplikat dalam daftar aturan.',
                ]);
            }
            $seen[$tingkatId] = true;

            $enabled = (bool) ($rule['is_enabled'] ?? true);
            $amountRaw = $rule['amount'] ?? null;
            $amount = $amountRaw === '' || $amountRaw === null ? null : round((float) $amountRaw, 2);
            $breakdown = PaymentType::normalizeBreakdownItems($rule['breakdown'] ?? []);

            if ($enabled && $amount !== null && $amount > 0 && $breakdown !== []) {
                $sum = PaymentType::breakdownTotal($breakdown);
                if (abs($sum - $amount) > 0.01) {
                    throw ValidationException::withMessages([
                        "tingkat_rules.$index.breakdown" => 'Total rincian tingkat harus sama dengan nominal aturan.',
                    ]);
                }
            }

            if ($enabled && ($amount === null || $amount <= 0)) {
                throw ValidationException::withMessages([
                    "tingkat_rules.$index.amount" => 'Isi nominal > 0 jika aturan aktif, atau nonaktifkan aturan.',
                ]);
            }

            if (! $enabled) {
                $amount = null;
                $breakdown = [];
            }

            $out[] = [
                'tingkat_sekolah_id' => $tingkatId,
                'is_enabled' => $enabled,
                'amount' => $amount,
                'breakdown' => $breakdown,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizePaymentTypePayload(array $validated): array
    {
        $breakdown = PaymentType::normalizeBreakdownItems($validated['default_breakdown'] ?? null);
        if ($breakdown !== []) {
            $sum = PaymentType::breakdownTotal($breakdown);
            if (abs($sum - (float) $validated['default_amount']) > 0.01) {
                throw ValidationException::withMessages([
                    'default_breakdown' => 'Total rincian default harus sama dengan nominal default.',
                ]);
            }
        }

        $validated['default_breakdown'] = $breakdown;

        return $validated;
    }
}
