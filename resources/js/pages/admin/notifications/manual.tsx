import axios from 'axios';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { BellRing, Search, Smartphone } from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import FlashMessage from '@/components/flash-message';
import InputError from '@/components/input-error';
import { AppMultiSelect, CrudCard, CrudPageHeader, CrudToolbar } from '@/components/manhood';
import type { SelectOption } from '@/components/manhood';
import AppLayout from '@/layouts/app-layout';
import { can } from '@/lib/authz';
import type { Auth, BreadcrumbItem } from '@/types';

type DeviceTokenLite = {
    id: number;
    platform: string;
    device_label: string | null;
    last_used_at: string | null;
    updated_at: string | null;
};

type UserWithTokens = {
    id: number;
    name: string;
    username: string | null;
    email: string;
    roles: string[];
    device_tokens_count: number;
    device_tokens: DeviceTokenLite[];
};

type Props = {
    filters: { search?: string };
    users: UserWithTokens[];
    summary: {
        users_with_tokens: number;
        device_tokens: number;
    };
};

type FormData = {
    title: string;
    body: string;
    deeplink: string;
    target_mode: 'single_user' | 'multi_user' | 'specific_devices';
    user_ids: number[];
    device_token_ids: number[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Kirim Notifikasi', href: '/admin/notifications/manual' },
];

export default function AdminManualNotificationPage({ filters, users, summary }: Props) {
    const { auth } = usePage<{ auth?: Auth }>().props;
    const canSend = can(auth, 'notification.manual.send');

    const [search, setSearch] = useState(filters.search ?? '');
    const [previewSummary, setPreviewSummary] = useState<{ eligible_users: number; eligible_device_tokens: number } | null>(null);

    const form = useForm<FormData>({
        title: '',
        body: '',
        deeplink: '/notifications',
        target_mode: 'single_user',
        user_ids: [],
        device_token_ids: [],
    });

    const [demoUserId, setDemoUserId] = useState('');
    const [demoSessionId, setDemoSessionId] = useState('');

    const userOptions = useMemo<SelectOption[]>(
        () =>
            users.map((user) => ({
                value: user.id,
                label: `${user.name} (${user.device_tokens_count} device)`,
            })),
        [users],
    );

    const selectedUsers = useMemo(
        () => users.filter((user) => form.data.user_ids.includes(user.id)),
        [users, form.data.user_ids],
    );

    const selectedUserOptions = userOptions.filter((option) => form.data.user_ids.includes(Number(option.value)));

    const eligibleDeviceCount = useMemo(() => {
        if (form.data.target_mode === 'specific_devices') {
            return form.data.device_token_ids.length;
        }

        return selectedUsers.reduce((sum, user) => sum + user.device_tokens_count, 0);
    }, [form.data.target_mode, form.data.device_token_ids.length, selectedUsers]);

    function applySearch() {
        router.get(
            '/admin/notifications/manual',
            { search: search.trim() || undefined },
            { preserveState: true, preserveScroll: true },
        );
    }

    async function handlePreview() {
        try {
            const { data } = await axios.post('/admin/notifications/manual/preview', {
                search: search.trim() || undefined,
                user_ids: form.data.user_ids,
            });
            setPreviewSummary(data.summary ?? null);
            toast.success('Preview target diperbarui');
        } catch {
            toast.error('Gagal memuat preview target');
        }
    }

    function handleTargetMode(mode: FormData['target_mode']) {
        form.setData((prev) => ({
            ...prev,
            target_mode: mode,
            user_ids: [],
            device_token_ids: [],
        }));
        setPreviewSummary(null);
    }

    function submitDemoOverlay(e: React.FormEvent) {
        e.preventDefault();
        if (!canSend) return;

        const uid = parseInt(demoUserId.trim(), 10);
        if (!Number.isFinite(uid) || uid <= 0) {
            toast.error('Isi User ID guru (angka valid).');
            return;
        }

        const payload: { user_id: number; lesson_session_id?: number } = { user_id: uid };
        if (demoSessionId.trim() !== '') {
            const sid = parseInt(demoSessionId.trim(), 10);
            if (!Number.isFinite(sid) || sid <= 0) {
                toast.error('ID sesi tidak valid.');
                return;
            }
            payload.lesson_session_id = sid;
        }

        router.post('/admin/notifications/manual/teacher-presence-overlay-demo', payload, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(
                    'Push demo terkirim. Biarkan aplikasi guru di foreground dan izin tampil di atas aplikasi lain aktif.',
                );
                setDemoUserId('');
                setDemoSessionId('');
            },
            onError: (errors) => {
                const msg =
                    errors.user_id?.[0] ??
                    errors.lesson_session_id?.[0] ??
                    errors.demo_overlay?.[0] ??
                    'Gagal mengirim demo overlay.';
                toast.error(String(msg));
            },
        });
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        if (!canSend) return;

        const isBulk = eligibleDeviceCount > 1;
        if (isBulk && !window.confirm(`Kirim notifikasi bulk ke ${eligibleDeviceCount} device target?`)) {
            return;
        }

        form.post('/admin/notifications/manual/send', {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(isBulk ? 'Notifikasi bulk dimasukkan ke antrean' : 'Notifikasi dikirim');
                form.reset('title', 'body', 'user_ids', 'device_token_ids');
                form.setData('deeplink', '/notifications');
            },
            onError: () => toast.error('Gagal mengirim notifikasi'),
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Kirim Notifikasi Manual" />
            <CrudPageHeader
                title="Kirim Notifikasi Manual"
                description="Kirim push notifikasi ke user/device yang memiliki token Firebase aktif."
            />

            <FlashMessage />

            {!canSend ? (
                <CrudCard title="Akses dibatasi" subtitle="Anda tidak memiliki permission untuk mengirim notifikasi manual.">
                    <p className="mcr-muted" style={{ fontSize: 13 }}>
                        Hubungi administrator jika Anda memerlukan akses ini.
                    </p>
                </CrudCard>
            ) : (
                <>
                    <CrudToolbar
                        left={
                            <div className="mcr-search" style={{ minWidth: 340 }}>
                                <Search size={15} />
                                <input
                                    placeholder="Cari user: nama, username, email"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    onKeyDown={(e) => (e.key === 'Enter' ? applySearch() : undefined)}
                                />
                            </div>
                        }
                        right={
                            <>
                                <button type="button" className="mcr-btn secondary" onClick={applySearch}>
                                    <Search size={14} />
                                    Cari
                                </button>
                                <button type="button" className="mcr-btn secondary" onClick={handlePreview}>
                                    Preview Target
                                </button>
                            </>
                        }
                    />

                    <CrudCard
                        title="Ringkasan Target"
                        subtitle={`Users bertoken: ${summary.users_with_tokens} • Device token: ${summary.device_tokens}`}
                        right={<span className="mcr-dot-badge active">{eligibleDeviceCount} eligible device</span>}
                    >
                        {previewSummary ? (
                            <div style={{ marginBottom: 10, color: 'var(--mhs-text-2)', fontSize: 13 }}>
                                Preview: {previewSummary.eligible_users} user • {previewSummary.eligible_device_tokens} device
                            </div>
                        ) : null}
                        <form onSubmit={handleSubmit}>
                            <div className="mcr-form-grid">
                                <div className="mcr-form-group full">
                                    <label>Mode Target</label>
                                    <select
                                        className="mcr-form-select"
                                        value={form.data.target_mode}
                                        onChange={(e) => handleTargetMode(e.target.value as FormData['target_mode'])}
                                    >
                                        <option value="single_user">Single User</option>
                                        <option value="multi_user">Multi User</option>
                                        <option value="specific_devices">Device Spesifik</option>
                                    </select>
                                </div>

                                <div className="mcr-form-group">
                                    <label>Judul</label>
                                    <input
                                        className="mcr-input"
                                        value={form.data.title}
                                        onChange={(e) => form.setData('title', e.target.value)}
                                    />
                                    <InputError message={form.errors.title} />
                                </div>

                                <div className="mcr-form-group">
                                    <label>Deeplink (opsional)</label>
                                    <input
                                        className="mcr-input"
                                        value={form.data.deeplink}
                                        onChange={(e) => form.setData('deeplink', e.target.value)}
                                    />
                                    <InputError message={form.errors.deeplink} />
                                </div>

                                <div className="mcr-form-group full">
                                    <label>Isi Pesan</label>
                                    <textarea
                                        className="mcr-textarea"
                                        rows={4}
                                        value={form.data.body}
                                        onChange={(e) => form.setData('body', e.target.value)}
                                    />
                                    <InputError message={form.errors.body} />
                                </div>

                                <div className="mcr-form-group full">
                                    <label>Pilih User Target</label>
                                    <AppMultiSelect
                                        options={userOptions}
                                        value={selectedUserOptions}
                                        onChange={(next) => {
                                            const ids = (next ?? []).map((item) => Number(item.value));
                                            form.setData('user_ids', form.data.target_mode === 'single_user' ? ids.slice(0, 1) : ids);
                                            if (form.data.target_mode !== 'specific_devices') {
                                                form.setData('device_token_ids', []);
                                            }
                                        }}
                                    />
                                    <InputError message={form.errors.user_ids} />
                                </div>
                            </div>

                            {form.data.target_mode === 'specific_devices' ? (
                                <div style={{ marginTop: 12 }}>
                                    <div style={{ fontWeight: 600, marginBottom: 8 }}>Pilih Device Spesifik</div>
                                    {selectedUsers.length === 0 ? (
                                        <div className="mcr-muted">Pilih user terlebih dahulu.</div>
                                    ) : (
                                        selectedUsers.map((user) => (
                                            <div key={user.id} className="mcr-run-item" style={{ marginBottom: 8 }}>
                                                <div style={{ marginBottom: 6, fontWeight: 600 }}>{user.name}</div>
                                                {user.device_tokens.map((token) => {
                                                    const checked = form.data.device_token_ids.includes(token.id);
                                                    return (
                                                        <label key={token.id} style={{ display: 'flex', gap: 8, alignItems: 'center', marginBottom: 4 }}>
                                                            <input
                                                                type="checkbox"
                                                                checked={checked}
                                                                onChange={(e) => {
                                                                    if (e.target.checked) {
                                                                        form.setData('device_token_ids', [...form.data.device_token_ids, token.id]);
                                                                    } else {
                                                                        form.setData(
                                                                            'device_token_ids',
                                                                            form.data.device_token_ids.filter((id) => id !== token.id),
                                                                        );
                                                                    }
                                                                }}
                                                            />
                                                            <span>
                                                                [{token.platform}] {token.device_label || `Device #${token.id}`}
                                                            </span>
                                                        </label>
                                                    );
                                                })}
                                            </div>
                                        ))
                                    )}
                                    <InputError message={form.errors.device_token_ids} />
                                </div>
                            ) : null}

                            <div style={{ marginTop: 12, display: 'flex', justifyContent: 'flex-end' }}>
                                <button type="submit" className="mcr-btn primary" disabled={form.processing}>
                                    <BellRing size={14} />
                                    {form.processing ? 'Mengirim...' : 'Kirim Notifikasi'}
                                </button>
                            </div>
                        </form>
                    </CrudCard>

                    <CrudCard
                        title="Demo overlay kehadiran guru (FCM)"
                        subtitle="Payload sama dengan reminder produksi (teacher_presence_confirmation_required) — untuk rekaman video Play Store / QA."
                    >
                        <p className="mcr-muted" style={{ fontSize: 13, marginBottom: 12 }}>
                            Push ini data-only: overlay bisa muncul saat aplikasi di <strong>background</strong> atau
                            foreground, asalkan token FCM aktif dan izin &quot;tampil di atas aplikasi lain&quot; sudah
                            pernah diberikan (Android).
                        </p>
                        <form onSubmit={submitDemoOverlay} className="mcr-form-grid">
                            <div className="mcr-form-group">
                                <label>User ID guru</label>
                                <input
                                    className="mcr-input"
                                    type="number"
                                    min={1}
                                    value={demoUserId}
                                    onChange={(e) => setDemoUserId(e.target.value)}
                                    placeholder="Lihat daftar user di atas"
                                />
                            </div>
                            <div className="mcr-form-group">
                                <label>ID sesi (opsional)</label>
                                <input
                                    className="mcr-input"
                                    type="number"
                                    min={1}
                                    value={demoSessionId}
                                    onChange={(e) => setDemoSessionId(e.target.value)}
                                    placeholder="lesson_sessions.id — kosong = sesi terbaru guru"
                                />
                            </div>
                            <div className="mcr-form-group full" style={{ marginTop: 4 }}>
                                <button type="submit" className="mcr-btn secondary">
                                    <Smartphone size={14} />
                                    Kirim push demo overlay
                                </button>
                            </div>
                        </form>
                    </CrudCard>
                </>
            )}
        </AppLayout>
    );
}
