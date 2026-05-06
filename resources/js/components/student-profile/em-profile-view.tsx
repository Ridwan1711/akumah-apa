import {
    BookOpen,
    Globe,
    Heart,
    Home,
    MapPin,
    Phone,
    Sparkles,
    Star,
    Users,
} from 'lucide-react';
import type { EmProfile } from '@/types';
import { ProfileInfoRow } from './profile-info-row';
import { ProfileSectionCard } from './profile-section-card';

type Props = {
    emProfile: EmProfile | null | undefined;
    /** Tampilkan catatan_khusus — hanya untuk admin */
    showCatatan?: boolean;
    /** Kompak — hide fields yang kosong */
    compact?: boolean;
};

function val(v: string | null | undefined): string | null {
    return v && v.trim() !== '' ? v : null;
}

export function EmProfileView({ emProfile, showCatatan = false, compact = false }: Props) {
    if (!emProfile) {
        return (
            <ProfileSectionCard icon={Star} title="Data EMIS" subtitle="Data profil EMIS santri">
                <div className="flex flex-col items-center justify-center py-10 text-center">
                    <div className="rounded-full bg-muted p-4 mb-3">
                        <Star size={24} className="text-muted-foreground" strokeWidth={1.5} />
                    </div>
                    <p className="text-sm font-medium text-foreground">Belum ada data EMIS</p>
                    <p className="text-xs text-muted-foreground mt-1">
                        Data EMIS belum diisi. Hubungi admin atau lengkapi melalui Edit Profil.
                    </p>
                </div>
            </ProfileSectionCard>
        );
    }

    const rows: { icon: React.ElementType; label: string; value: string | null; mono?: boolean }[] = [
        { icon: BookOpen, label: 'NISN',              value: val(emProfile.nisn), mono: true },
        { icon: BookOpen, label: 'NISM',              value: val(emProfile.nism), mono: true },
        { icon: Globe,    label: 'Kewarganegaraan',   value: val(emProfile.kewarganegaraan) },
        { icon: Heart,    label: 'Agama',             value: val(emProfile.agama) },
        { icon: Users,    label: 'Anak Ke',           value: val(emProfile.anak_ke) },
        { icon: Users,    label: 'Jumlah Saudara',    value: val(emProfile.jumlah_saudara) },
        { icon: Phone,    label: 'No HP Santri',      value: val(emProfile.no_hp) },
        { icon: Sparkles, label: 'Cita-cita',         value: val(emProfile.cita_cita) },
        { icon: Sparkles, label: 'Hobi',              value: val(emProfile.hobi) },
        { icon: BookOpen, label: 'Pendidikan Sebelumnya', value: val(emProfile.pendidikan_sebelumnya) },
        { icon: Home,     label: 'Status Mukim',      value: val(emProfile.status_mukim) },
        { icon: Home,     label: 'Status Tempat Tinggal', value: val(emProfile.status_tempat_tinggal) },
        { icon: MapPin,   label: 'Asal Daerah',       value: val(emProfile.asal_daerah) },
    ];

    if (showCatatan) {
        rows.push({ icon: BookOpen, label: 'Catatan Khusus', value: val(emProfile.catatan_khusus) });
    }

    const visibleRows = compact ? rows.filter((r) => r.value !== null) : rows;

    return (
        <ProfileSectionCard
            icon={Star}
            title="Data EMIS"
            subtitle="Data profil EMIS santri — NISN, agama, status mukim, dan lainnya"
        >
            {visibleRows.map((row) => (
                <ProfileInfoRow
                    key={row.label}
                    icon={row.icon}
                    label={row.label}
                    value={row.value}
                    mono={row.mono}
                />
            ))}
        </ProfileSectionCard>
    );
}
