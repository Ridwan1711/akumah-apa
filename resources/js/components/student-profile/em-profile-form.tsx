import { Star } from 'lucide-react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ProfileSectionCard } from './profile-section-card';

export type EmProfileFormData = {
    nisn: string;
    nism: string;
    kewarganegaraan: string;
    agama: string;
    anak_ke: string;
    jumlah_saudara: string;
    no_hp: string;
    cita_cita: string;
    hobi: string;
    pendidikan_sebelumnya: string;
    status_mukim: string;
    status_tempat_tinggal: string;
    asal_daerah: string;
    catatan_khusus: string;
};

type EmProfileSource = Partial<Record<keyof EmProfileFormData, string | null | undefined>>;

export function emProfileDefaults(ep?: EmProfileSource | null): EmProfileFormData {
    return {
        nisn: ep?.nisn ?? '',
        nism: ep?.nism ?? '',
        kewarganegaraan: ep?.kewarganegaraan ?? '',
        agama: ep?.agama ?? '',
        anak_ke: ep?.anak_ke ?? '',
        jumlah_saudara: ep?.jumlah_saudara ?? '',
        no_hp: ep?.no_hp ?? '',
        cita_cita: ep?.cita_cita ?? '',
        hobi: ep?.hobi ?? '',
        pendidikan_sebelumnya: ep?.pendidikan_sebelumnya ?? '',
        status_mukim: ep?.status_mukim ?? '',
        status_tempat_tinggal: ep?.status_tempat_tinggal ?? '',
        asal_daerah: ep?.asal_daerah ?? '',
        catatan_khusus: ep?.catatan_khusus ?? '',
    };
}

const AGAMA_OPTIONS = ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu'];
const STATUS_MUKIM = ['Mukim', 'Tidak Mukim'];
const STATUS_TINGGAL = ['Bersama Orang Tua', 'Wali', 'Kos/Kontrak', 'Asrama/Pesantren', 'Lainnya'];

type FieldProps = {
    label: string;
    required?: boolean;
    span2?: boolean;
    children: React.ReactNode;
    error?: string;
};

function Field({ label, required, span2, children, error }: FieldProps) {
    return (
        <div className={`flex flex-col gap-1.5 ${span2 ? 'sm:col-span-2' : ''}`}>
            <label className="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                {label}
                {required && <span className="ml-1 text-rose-500">*</span>}
            </label>
            {children}
            {error && <InputError message={error} />}
        </div>
    );
}

type Props = {
    data: EmProfileFormData;
    errors?: Partial<Record<keyof EmProfileFormData, string>>;
    onChange: (field: keyof EmProfileFormData, value: string) => void;
    /** Tampilkan field catatan_khusus — hanya untuk admin */
    showCatatan?: boolean;
    /** NISM diisi sistem dari NIS; tidak boleh diedit manual */
    nismReadOnly?: boolean;
};

export function EmProfileForm({ data, errors = {}, onChange, showCatatan = false, nismReadOnly = false }: Props) {
    const inp = (field: keyof EmProfileFormData) => ({
        value: data[field] as string,
        onChange: (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) =>
            onChange(field, e.target.value),
        className:
            'h-11 rounded-xl border-border bg-white text-sm text-foreground shadow-sm transition-all placeholder:text-muted-foreground focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500',
    });

    return (
        <ProfileSectionCard
            icon={Star}
            title="Data EMIS"
            subtitle="Lengkapi data EMIS santri untuk keperluan pelaporan"
        >
            <div className="py-6 grid gap-5 sm:grid-cols-2">
                <Field label="NISN" error={errors.nisn}>
                    <Input {...inp('nisn')} placeholder="10 digit NISN" maxLength={20} className={`${inp('nisn').className} font-mono tracking-widest`} />
                </Field>

                <Field label="NISM" error={errors.nism}>
                    {nismReadOnly ? (
                        <>
                            <Input
                                value={data.nism}
                                readOnly
                                tabIndex={-1}
                                className={`${inp('nism').className} font-mono cursor-default bg-muted/40`}
                            />
                            <p className="text-muted-foreground text-xs">
                                Diisi otomatis dari NIS dan tahun masuk (format madrasah).
                            </p>
                        </>
                    ) : (
                        <Input {...inp('nism')} placeholder="Nomor Induk Siswa Madrasah" className={`${inp('nism').className} font-mono`} />
                    )}
                </Field>

                <Field label="Agama" error={errors.agama}>
                    <Select value={data.agama} onValueChange={(v) => onChange('agama', v)}>
                        <SelectTrigger className="h-11 rounded-xl border-border bg-white text-sm shadow-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                            <SelectValue placeholder="Pilih agama" />
                        </SelectTrigger>
                        <SelectContent>
                            {AGAMA_OPTIONS.map((a) => (
                                <SelectItem key={a} value={a}>{a}</SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </Field>

                <Field label="Kewarganegaraan" error={errors.kewarganegaraan}>
                    <Input {...inp('kewarganegaraan')} placeholder="WNI / WNA" />
                </Field>

                <Field label="Anak Ke" error={errors.anak_ke}>
                    <Input {...inp('anak_ke')} type="number" min={1} placeholder="misal: 2" />
                </Field>

                <Field label="Jumlah Saudara" error={errors.jumlah_saudara}>
                    <Input {...inp('jumlah_saudara')} type="number" min={0} placeholder="misal: 3" />
                </Field>

                <Field label="No HP Santri" error={errors.no_hp}>
                    <Input {...inp('no_hp')} placeholder="08xx-xxxx-xxxx" />
                </Field>

                <Field label="Pendidikan Sebelumnya" error={errors.pendidikan_sebelumnya}>
                    <Input {...inp('pendidikan_sebelumnya')} placeholder="misal: SD Negeri 1" />
                </Field>

                <Field label="Status Mukim" error={errors.status_mukim}>
                    <Select value={data.status_mukim} onValueChange={(v) => onChange('status_mukim', v)}>
                        <SelectTrigger className="h-11 rounded-xl border-border bg-white text-sm shadow-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                            <SelectValue placeholder="Pilih status" />
                        </SelectTrigger>
                        <SelectContent>
                            {STATUS_MUKIM.map((s) => (
                                <SelectItem key={s} value={s}>{s}</SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </Field>

                <Field label="Status Tempat Tinggal" error={errors.status_tempat_tinggal}>
                    <Select value={data.status_tempat_tinggal} onValueChange={(v) => onChange('status_tempat_tinggal', v)}>
                        <SelectTrigger className="h-11 rounded-xl border-border bg-white text-sm shadow-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                            <SelectValue placeholder="Pilih status" />
                        </SelectTrigger>
                        <SelectContent>
                            {STATUS_TINGGAL.map((s) => (
                                <SelectItem key={s} value={s}>{s}</SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </Field>

                <Field label="Asal Daerah" error={errors.asal_daerah}>
                    <Input {...inp('asal_daerah')} placeholder="misal: Surabaya, Jawa Timur" />
                </Field>

                <Field label="Cita-cita" error={errors.cita_cita}>
                    <Input {...inp('cita_cita')} placeholder="misal: Dokter" />
                </Field>

                <Field label="Hobi" span2 error={errors.hobi}>
                    <Input {...inp('hobi')} placeholder="misal: Membaca, Sepakbola" />
                </Field>

                {showCatatan && (
                    <Field label="Catatan Khusus" span2 error={errors.catatan_khusus}>
                        <textarea
                            value={data.catatan_khusus}
                            onChange={(e) => onChange('catatan_khusus', e.target.value)}
                            rows={3}
                            className="w-full rounded-xl border border-border bg-white px-4 py-3 text-sm text-foreground shadow-sm placeholder:text-muted-foreground transition-all focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                            placeholder="Catatan khusus admin (tidak terlihat santri)"
                        />
                    </Field>
                )}
            </div>
        </ProfileSectionCard>
    );
}
