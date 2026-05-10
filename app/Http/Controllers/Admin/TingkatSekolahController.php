<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTingkatSekolahRequest;
use App\Http\Requests\Admin\UpdateTingkatSekolahRequest;
use App\Models\TingkatSekolah;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TingkatSekolahController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/tingkat-sekolahs/index', [
            'tingkatSekolahs' => TingkatSekolah::query()
                ->orderBy('order')
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'group', 'order', 'is_billable']),
            'knownCodes' => TingkatSekolah::CODES,
        ]);
    }

    public function store(StoreTingkatSekolahRequest $request): RedirectResponse
    {
        TingkatSekolah::query()->create($request->tingkatPayload());

        return redirect()->route('admin.tingkat-sekolahs.index')
            ->with('success', 'Tingkat sekolah formal berhasil ditambahkan.');
    }

    public function update(UpdateTingkatSekolahRequest $request, TingkatSekolah $tingkatSekolah): RedirectResponse
    {
        $tingkatSekolah->update($request->tingkatPayload());

        return redirect()->route('admin.tingkat-sekolahs.index')
            ->with('success', 'Tingkat sekolah formal berhasil diperbarui.');
    }

    public function destroy(TingkatSekolah $tingkatSekolah): RedirectResponse
    {
        if ($tingkatSekolah->enrollmentTingkatSekolahs()->exists()) {
            return redirect()->route('admin.tingkat-sekolahs.index')
                ->with('error', 'Tidak dapat menghapus tingkat yang masih dipakai enrollment santri.');
        }

        if ($tingkatSekolah->paymentTypeRules()->exists()) {
            return redirect()->route('admin.tingkat-sekolahs.index')
                ->with('error', 'Tidak dapat menghapus tingkat yang masih punya aturan tarif pada jenis tagihan.');
        }

        if ($tingkatSekolah->invoices()->exists()) {
            return redirect()->route('admin.tingkat-sekolahs.index')
                ->with('error', 'Tidak dapat menghapus tingkat yang masih direferensi tagihan.');
        }

        $tingkatSekolah->delete();

        return redirect()->route('admin.tingkat-sekolahs.index')
            ->with('success', 'Tingkat sekolah formal berhasil dihapus.');
    }
}
