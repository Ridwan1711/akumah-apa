import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2, WalletCards } from 'lucide-react';
import { useMemo, useState } from 'react';
import FlashMessage from '@/components/flash-message';
import InputError from '@/components/input-error';
import {
    CrudCard,
    CrudConfirmModal,
    CrudEmptyState,
    CrudModal,
    CrudPageHeader,
    CrudStatStrip,
    CrudTableShell,
    CrudToolbar,
} from '@/components/manhood';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, PaymentType, PaymentTypeTingkatRule, TingkatSekolahFormal } from '@/types';

type Props = {
    paymentTypes: PaymentType[];
    tingkatSekolahs: TingkatSekolahFormal[];
};

type BreakdownItemForm = {
    label: string;
    amount: string;
};

type TingkatRuleRowForm = {
    tingkat_sekolah_id: number;
    tingkat_label: string;
    is_enabled: boolean;
    amount: string;
};

type PaymentTypeForm = {
    name: string;
    code: string;
    category: PaymentType['category'];
    is_recurring: boolean;
    default_amount: string;
    kuliah_amount: string;
    default_breakdown: BreakdownItemForm[];
    tingkat_rules: TingkatRuleRowForm[];
    description: string;
    is_active: boolean;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Jenis Pembayaran', href: '/admin/payment-types' },
];

const categoryLabels: Record<PaymentType['category'], string> = {
    spp: 'SPP',
    non_spp: 'Non-SPP',
    infaq: 'Infaq',
};

function toCurrency(value: number | string): string {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(Number(value));
}

function buildTingkatRules(tingkatList: TingkatSekolahFormal[], editing: PaymentType | null): TingkatRuleRowForm[] {
    const map = new Map<number, PaymentTypeTingkatRule>();
    const rules = editing?.tingkat_rules ?? [];
    rules.forEach((r) => map.set(r.tingkat_sekolah_id, r));

    return tingkatList.map((t) => {
        const ex = map.get(t.id);
        const amt = ex?.amount;

        return {
            tingkat_sekolah_id: t.id,
            tingkat_label: t.group ? `${t.name} (${t.group})` : t.name,
            is_enabled: ex?.is_enabled ?? false,
            amount: amt !== null && amt !== undefined && amt !== '' ? String(amt) : '',
        };
    });
}

function countEnabledTingkatRules(item: PaymentType): number {
    return (item.tingkat_rules ?? []).filter((r) => r.is_enabled).length;
}

export default function PaymentTypesIndex({ paymentTypes, tingkatSekolahs }: Props) {
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<PaymentType | null>(null);
    const [deleting, setDeleting] = useState<PaymentType | null>(null);

    const form = useForm<PaymentTypeForm>({
        name: '',
        code: '',
        category: 'spp',
        is_recurring: true,
        default_amount: '0',
        kuliah_amount: '',
        default_breakdown: [],
        tingkat_rules: buildTingkatRules(tingkatSekolahs, null),
        description: '',
        is_active: true,
    });

    const activeCount = useMemo(() => paymentTypes.filter((item) => item.is_active).length, [paymentTypes]);

    function openCreate() {
        setEditing(null);
        form.setData({
            name: '',
            code: '',
            category: 'spp',
            is_recurring: true,
            default_amount: '0',
            kuliah_amount: '',
            default_breakdown: [],
            tingkat_rules: buildTingkatRules(tingkatSekolahs, null),
            description: '',
            is_active: true,
        });
        form.clearErrors();
        setModalOpen(true);
    }

    function openEdit(item: PaymentType) {
        setEditing(item);
        form.setData({
            name: item.name,
            code: item.code,
            category: item.category,
            is_recurring: item.is_recurring,
            default_amount: String(item.default_amount),
            kuliah_amount: item.kuliah_amount === null || item.kuliah_amount === undefined ? '' : String(item.kuliah_amount),
            default_breakdown: (item.default_breakdown ?? []).map((part) => ({
                label: part.label,
                amount: String(part.amount),
            })),
            tingkat_rules: buildTingkatRules(tingkatSekolahs, item),
            description: item.description ?? '',
            is_active: item.is_active,
        });
        form.clearErrors();
        setModalOpen(true);
    }

    function setTingkatRuleField(index: number, patch: Partial<TingkatRuleRowForm>) {
        form.setData(
            'tingkat_rules',
            form.data.tingkat_rules.map((row, i) => (i === index ? { ...row, ...patch } : row)),
        );
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            default_amount: Number(data.default_amount),
            kuliah_amount: data.kuliah_amount === '' ? null : Number(data.kuliah_amount),
            default_breakdown: data.default_breakdown
                .filter((item) => item.label.trim() !== '' && item.amount !== '')
                .map((item) => ({
                    label: item.label.trim(),
                    amount: Number(item.amount),
                })),
            tingkat_rules: data.tingkat_rules.map((row) => ({
                tingkat_sekolah_id: row.tingkat_sekolah_id,
                is_enabled: row.is_enabled,
                amount: row.is_enabled && row.amount !== '' ? Number(row.amount) : null,
            })),
        }));

        if (editing) {
            form.put(`/admin/payment-types/${editing.id}`, {
                preserveScroll: true,
                onSuccess: () => setModalOpen(false),
                onFinish: () => form.transform((data) => data),
            });
            return;
        }

        form.post('/admin/payment-types', {
            preserveScroll: true,
            onSuccess: () => setModalOpen(false),
            onFinish: () => form.transform((data) => data),
        });
    }

    function destroy() {
        if (!deleting) return;
        router.delete(`/admin/payment-types/${deleting.id}`, {
            preserveScroll: true,
            onFinish: () => setDeleting(null),
        });
    }

    function appendBreakdownRow() {
        form.setData('default_breakdown', [...form.data.default_breakdown, { label: '', amount: '' }]);
    }

    function removeBreakdownRow(index: number) {
        form.setData(
            'default_breakdown',
            form.data.default_breakdown.filter((_, i) => i !== index),
        );
    }

    function setBreakdownField(index: number, field: keyof BreakdownItemForm, value: string) {
        form.setData(
            'default_breakdown',
            form.data.default_breakdown.map((item, i) => (i === index ? { ...item, [field]: value } : item)),
        );
    }

    const breakdownTotal = useMemo(
        () => form.data.default_breakdown.reduce((sum, item) => sum + (Number(item.amount) || 0), 0),
        [form.data.default_breakdown],
    );
    const defaultAmountNumber = Number(form.data.default_amount) || 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Jenis Pembayaran" />
            <div>
                <CrudPageHeader
                    title="Jenis Pembayaran"
                    description="Master tipe tagihan. Atur tarif default dan per tingkat sekolah formal (MTs/MA/Kuliah) untuk pembentukan invoice."
                />

                <CrudStatStrip
                    items={[
                        { key: 'total', label: 'Total Jenis', value: paymentTypes.length, icon: <WalletCards size={18} />, tone: 'blue' },
                        { key: 'active', label: 'Aktif', value: activeCount, icon: <WalletCards size={18} />, tone: 'green' },
                        { key: 'recurring', label: 'Berulang', value: paymentTypes.filter((item) => item.is_recurring).length, icon: <WalletCards size={18} />, tone: 'amber' },
                        { key: 'inactive', label: 'Nonaktif', value: paymentTypes.length - activeCount, icon: <WalletCards size={18} />, tone: 'purple' },
                    ]}
                />

                <FlashMessage />

                <CrudToolbar
                    left={null}
                    right={
                        <button type="button" className="mcr-btn primary" onClick={openCreate}>
                            <Plus size={14} />
                            Tambah Jenis
                        </button>
                    }
                />

                <CrudCard title="Daftar Jenis Pembayaran">
                    <CrudTableShell>
                        <table className="mcr-table">
                            <thead>
                                <tr>
                                    <th>Nama</th>
                                    <th>Kode</th>
                                    <th>Kategori</th>
                                    <th>Default</th>
                                    <th>Aturan formal</th>
                                    <th>Kuliah (legacy)</th>
                                    <th>Status</th>
                                    <th style={{ textAlign: 'right' }}>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {paymentTypes.length === 0 ? (
                                    <tr>
                                        <td colSpan={8}>
                                            <CrudEmptyState title="Belum ada jenis pembayaran" description="Tambahkan jenis pembayaran pertama." />
                                        </td>
                                    </tr>
                                ) : (
                                    paymentTypes.map((item) => (
                                        <tr key={item.id}>
                                            <td>
                                                <div style={{ fontWeight: 600 }}>{item.name}</div>
                                                <div className="mcr-table-meta">{item.description ?? '-'}</div>
                                            </td>
                                            <td><code>{item.code}</code></td>
                                            <td>{categoryLabels[item.category]}</td>
                                            <td>{toCurrency(item.default_amount)}</td>
                                            <td>
                                                <span className="mcr-dot-badge keluar">{countEnabledTingkatRules(item)} aktif</span>
                                            </td>
                                            <td>{item.kuliah_amount !== null && item.kuliah_amount !== undefined ? toCurrency(item.kuliah_amount) : '-'}</td>
                                            <td>
                                                <span className={item.is_active ? 'mcr-dot-badge active' : 'mcr-dot-badge keluar'}>
                                                    {item.is_active ? 'Aktif' : 'Nonaktif'}
                                                </span>
                                            </td>
                                            <td>
                                                <div className="mcr-action-group">
                                                    <button type="button" className="mcr-icon-action" onClick={() => openEdit(item)} title="Edit">
                                                        <Pencil size={13} />
                                                    </button>
                                                    <button type="button" className="mcr-icon-action danger" onClick={() => setDeleting(item)} title="Hapus">
                                                        <Trash2 size={13} />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </CrudTableShell>
                </CrudCard>
            </div>

            <CrudModal
                open={modalOpen}
                onClose={() => setModalOpen(false)}
                title={editing ? 'Edit Jenis Pembayaran' : 'Tambah Jenis Pembayaran'}
                footer={(
                    <>
                        <button type="button" className="mcr-btn ghost" onClick={() => setModalOpen(false)} disabled={form.processing}>
                            Batal
                        </button>
                        <button type="submit" form="payment-type-form" className="mcr-btn primary" disabled={form.processing}>
                            {form.processing ? 'Menyimpan...' : 'Simpan'}
                        </button>
                    </>
                )}
            >
                <form id="payment-type-form" className="mcr-form-grid" onSubmit={submit}>
                    <div className="mcr-form-group">
                        <label htmlFor="payment-type-name">Nama</label>
                        <input id="payment-type-name" className="mcr-input" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="mcr-form-group">
                        <label htmlFor="payment-type-code">Kode</label>
                        <input id="payment-type-code" className="mcr-input" value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} />
                        <InputError message={form.errors.code} />
                    </div>
                    <div className="mcr-form-group">
                        <label htmlFor="payment-type-category">Kategori</label>
                        <select id="payment-type-category" className="mcr-form-select" value={form.data.category} onChange={(e) => form.setData('category', e.target.value as PaymentType['category'])}>
                            <option value="spp">SPP</option>
                            <option value="non_spp">Non-SPP</option>
                            <option value="infaq">Infaq</option>
                        </select>
                        <InputError message={form.errors.category} />
                    </div>
                    <div className="mcr-form-group">
                        <label htmlFor="payment-type-default">Fallback nominal (tanpa / belum ada aturan formal)</label>
                        <input id="payment-type-default" className="mcr-input" type="number" min={0} value={form.data.default_amount} onChange={(e) => form.setData('default_amount', e.target.value)} />
                        <InputError message={form.errors.default_amount} />
                    </div>
                    <div className="mcr-form-group">
                        <label htmlFor="payment-type-kuliah">Nominal santri kuliah tanpa enrollment formal (legacy)</label>
                        <input id="payment-type-kuliah" className="mcr-input" type="number" min={0} value={form.data.kuliah_amount} onChange={(e) => form.setData('kuliah_amount', e.target.value)} />
                        <InputError message={form.errors.kuliah_amount} />
                    </div>
                    <div className="mcr-form-group full">
                        <label htmlFor="payment-type-description">Deskripsi</label>
                        <textarea id="payment-type-description" className="mcr-textarea" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                        <InputError message={form.errors.description} />
                    </div>
                    <div className="mcr-form-group full">
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 8 }}>
                            <label>Rincian Default (fallback skala nominal)</label>
                            <button type="button" className="mcr-btn ghost" onClick={appendBreakdownRow}>
                                <Plus size={14} />
                                Tambah Rincian
                            </button>
                        </div>
                        {form.data.default_breakdown.length === 0 ? (
                            <p className="mcr-table-meta">Belum ada rincian default. Invoice akan tanpa rincian sampai diisi.</p>
                        ) : (
                            <div style={{ display: 'grid', gap: 8 }}>
                                {form.data.default_breakdown.map((item, index) => (
                                    <div key={`breakdown-${index}`} style={{ display: 'grid', gridTemplateColumns: '1fr 180px auto', gap: 8 }}>
                                        <input
                                            className="mcr-input"
                                            placeholder="Nama rincian, contoh: Uang makan"
                                            value={item.label}
                                            onChange={(e) => setBreakdownField(index, 'label', e.target.value)}
                                        />
                                        <input
                                            className="mcr-input"
                                            type="number"
                                            min={0}
                                            placeholder="Nominal"
                                            value={item.amount}
                                            onChange={(e) => setBreakdownField(index, 'amount', e.target.value)}
                                        />
                                        <button type="button" className="mcr-btn ghost" onClick={() => removeBreakdownRow(index)}>
                                            Hapus
                                        </button>
                                    </div>
                                ))}
                            </div>
                        )}
                        <p className="mcr-table-meta" style={{ marginTop: 8 }}>
                            Total rincian: {toCurrency(breakdownTotal)} • Nominal default: {toCurrency(defaultAmountNumber)}
                        </p>
                        <InputError message={form.errors.default_breakdown} />
                    </div>

                    <div className="mcr-form-group full">
                        <label>Tarif per tingkat formal</label>
                        <p className="mcr-table-meta" style={{ marginBottom: 8 }}>
                            Centang aktif untuk membebankan santri sesuai enrollment tingkat sekolah tahun ajaran. Nonaktif = tidak ada tagihan (mis. Kuliah di luar pesantren).
                        </p>
                        <div style={{ display: 'grid', gap: 10 }}>
                            {form.data.tingkat_rules.map((row, index) => (
                                <div
                                    key={row.tingkat_sekolah_id}
                                    style={{
                                        display: 'grid',
                                        gridTemplateColumns: 'minmax(0, 1fr) 120px auto',
                                        gap: 10,
                                        alignItems: 'center',
                                        padding: '8px 0',
                                        borderBottom: index < form.data.tingkat_rules.length - 1 ? '1px solid var(--mhs-card-border-soft, rgba(0,0,0,0.08))' : undefined,
                                    }}
                                >
                                    <label style={{ display: 'flex', gap: 8, alignItems: 'center', margin: 0 }}>
                                        <input
                                            type="checkbox"
                                            checked={row.is_enabled}
                                            onChange={(e) => setTingkatRuleField(index, { is_enabled: e.target.checked })}
                                        />
                                        <span style={{ fontWeight: 500 }}>{row.tingkat_label}</span>
                                    </label>
                                    <input
                                        className="mcr-input"
                                        type="number"
                                        min={0}
                                        placeholder="Rp"
                                        disabled={!row.is_enabled}
                                        value={row.amount}
                                        onChange={(e) => setTingkatRuleField(index, { amount: e.target.value })}
                                    />
                                </div>
                            ))}
                        </div>
                        <InputError message={form.errors.tingkat_rules as unknown as string} />
                    </div>

                    <label className="mcr-form-group">
                        <span>Berulang</span>
                        <input type="checkbox" checked={form.data.is_recurring} onChange={(e) => form.setData('is_recurring', e.target.checked)} />
                    </label>
                    <label className="mcr-form-group">
                        <span>Aktif</span>
                        <input type="checkbox" checked={form.data.is_active} onChange={(e) => form.setData('is_active', e.target.checked)} />
                    </label>
                </form>
            </CrudModal>

            <CrudConfirmModal
                open={deleting !== null}
                onClose={() => setDeleting(null)}
                onConfirm={destroy}
                title="Hapus Jenis Pembayaran"
                description={`Hapus jenis pembayaran "${deleting?.name ?? ''}"?`}
                confirmLabel="Hapus"
            />
        </AppLayout>
    );
}
