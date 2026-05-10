import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { CrudModal } from '@/components/manhood';

type Props = {
    open: boolean;
    onClose: () => void;
    invoiceId: number;
    invoiceNumber?: string;
};

export function InvoiceSendReminderModal({ open, onClose, invoiceId, invoiceNumber }: Props) {
    const [channelError, setChannelError] = useState('');
    const form = useForm({
        message: '',
        send_app_notification: true as boolean,
        send_whatsapp: false as boolean,
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        if (!form.data.send_app_notification && !form.data.send_whatsapp) {
            setChannelError('Pilih minimal satu saluran.');
            return;
        }
        setChannelError('');
        form.post(`/admin/invoices/${invoiceId}/send-reminder`, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onClose();
            },
        });
    }

    return (
        <CrudModal
            open={open}
            onClose={() => {
                if (!form.processing) {
                    onClose();
                }
            }}
            title="Kirim pengingat tagihan"
            subtitle={invoiceNumber ? `Invoice ${invoiceNumber}` : undefined}
            footer={(
                <div className="mcr-action-group" style={{ justifyContent: 'flex-end', width: '100%' }}>
                    <button type="button" className="mcr-btn ghost" onClick={onClose} disabled={form.processing}>
                        Batal
                    </button>
                    <button type="submit" form="invoice-send-reminder-form" className="mcr-btn primary" disabled={form.processing}>
                        {form.processing ? 'Mengirim…' : 'Kirim'}
                    </button>
                </div>
            )}
        >
            <form id="invoice-send-reminder-form" onSubmit={submit}>
                <p className="mcr-table-meta" style={{ marginBottom: 10 }}>
                    Pesan opsional menggantikan template otomatis bila diisi. Kosongkan untuk memakai template resmi (termasuk rincian tagihan).
                </p>
                <label style={{ fontWeight: 600, fontSize: 12, marginBottom: 4, display: 'block' }} htmlFor="reminder-message">
                    Pesan tambahan (opsional)
                </label>
                <textarea
                    id="reminder-message"
                    className="mcr-input"
                    rows={4}
                    maxLength={500}
                    value={form.data.message}
                    onChange={(e) => form.setData('message', e.target.value)}
                    placeholder="Kosongkan untuk template otomatis…"
                    style={{ width: '100%', minHeight: 88, resize: 'vertical' }}
                />
                {form.errors.message ? <p className="mcr-table-meta" style={{ color: '#dc2626' }}>{form.errors.message}</p> : null}

                <div style={{ marginTop: 14 }}>
                    <div style={{ fontWeight: 600, fontSize: 12 }}>Saluran</div>
                    <label style={{ display: 'flex', alignItems: 'center', gap: 8, marginTop: 6 }}>
                        <input
                            type="checkbox"
                            checked={form.data.send_app_notification}
                            onChange={(e) => form.setData('send_app_notification', e.target.checked)}
                        />
                        <span>Notifikasi aplikasi (in-app + push)</span>
                    </label>
                    <label style={{ display: 'flex', alignItems: 'center', gap: 8, marginTop: 6 }}>
                        <input
                            type="checkbox"
                            checked={form.data.send_whatsapp}
                            onChange={(e) => form.setData('send_whatsapp', e.target.checked)}
                        />
                        <span>WhatsApp (perlu WA_ENABLED & worker antrean di server)</span>
                    </label>
                    {channelError ? <p className="mcr-table-meta" style={{ color: '#dc2626', marginTop: 6 }}>{channelError}</p> : null}
                    {form.errors.send_app_notification ? (
                        <p className="mcr-table-meta" style={{ color: '#dc2626', marginTop: 6 }}>{form.errors.send_app_notification}</p>
                    ) : null}
                </div>
            </form>
        </CrudModal>
    );
}
