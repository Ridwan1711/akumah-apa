import { Head, router, useForm } from '@inertiajs/react';
import { BedDouble, Building2, Pencil, Plus, Trash2, Users } from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import FlashMessage from '@/components/flash-message';
import InputError from '@/components/input-error';
import {
    CrudCard,
    CrudConfirmModal,
    CrudEmptyState,
    CrudModal,
    CrudPageHeader,
    CrudStatStrip,
    CrudToolbar,
} from '@/components/manhood';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, DormBuilding, DormRoom } from '@/types';

type BuildingRow = DormBuilding & {
    rooms?: (DormRoom & { musyrif?: { user?: { name?: string } | null } | null })[];
};

type Props = {
    buildings: BuildingRow[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Asrama', href: '/admin/asrama' },
];

type BuildingForm = { name: string; description: string };
type RoomForm = { building_id: string; room_number: string; capacity: string; floor: string };

export default function AsramaIndex({ buildings }: Props) {
    const [buildingModalOpen, setBuildingModalOpen] = useState(false);
    const [roomModalOpen, setRoomModalOpen] = useState(false);
    const [deleteBuildingTarget, setDeleteBuildingTarget] = useState<BuildingRow | null>(null);
    const [deleteRoomTarget, setDeleteRoomTarget] = useState<DormRoom | null>(null);

    const buildingForm = useForm<BuildingForm>({ name: '', description: '' });
    const roomForm = useForm<RoomForm>({ building_id: '', room_number: '', capacity: '4', floor: '' });

    const roomCount = useMemo(
        () => buildings.reduce((sum, b) => sum + (b.rooms?.length ?? 0), 0),
        [buildings],
    );
    const occupantCount = useMemo(
        () =>
            buildings.reduce(
                (sum, b) =>
                    sum + (b.rooms?.reduce((sub, r) => sub + (Number(r.occupants_count ?? 0) || 0), 0) ?? 0),
                0,
            ),
        [buildings],
    );

    function submitBuilding(e: React.FormEvent) {
        e.preventDefault();
        buildingForm.post('/admin/asrama/buildings', {
            onSuccess: () => {
                setBuildingModalOpen(false);
                buildingForm.reset();
                toast.success('Gedung ditambahkan');
            },
            onError: () => toast.error('Gagal menambah gedung'),
        });
    }

    function submitRoom(e: React.FormEvent) {
        e.preventDefault();
        roomForm.post('/admin/asrama/rooms', {
            onSuccess: () => {
                setRoomModalOpen(false);
                roomForm.reset();
                toast.success('Kamar ditambahkan');
            },
            onError: () => toast.error('Gagal menambah kamar'),
        });
    }

    function deleteBuilding() {
        if (!deleteBuildingTarget) return;
        router.delete(`/admin/asrama/buildings/${deleteBuildingTarget.id}`, {
            onSuccess: () => {
                setDeleteBuildingTarget(null);
                toast.success('Gedung dihapus');
            },
            onError: () => toast.error('Gagal menghapus gedung'),
        });
    }

    function deleteRoom() {
        if (!deleteRoomTarget) return;
        router.delete(`/admin/asrama/rooms/${deleteRoomTarget.id}`, {
            onSuccess: () => {
                setDeleteRoomTarget(null);
                toast.success('Kamar dihapus');
            },
            onError: () => toast.error('Gagal menghapus kamar'),
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Asrama" />
            <div>
                <CrudPageHeader title="Manajemen Asrama" description="Kelola gedung, kamar, dan kapasitas asrama." />
                <CrudStatStrip
                    items={[
                        { key: 'building', label: 'Total Gedung', value: buildings.length, icon: <Building2 size={18} />, tone: 'blue' },
                        { key: 'room', label: 'Total Kamar', value: roomCount, icon: <BedDouble size={18} />, tone: 'green' },
                        { key: 'occupant', label: 'Total Penghuni', value: occupantCount, icon: <Users size={18} />, tone: 'amber' },
                        { key: 'assign', label: 'Penempatan', value: 'Asrama/Assign', icon: <Pencil size={18} />, tone: 'purple' },
                    ]}
                />

                <FlashMessage />

                <CrudToolbar
                    left={<span className="mcr-table-meta">Tambahkan gedung dan kamar, lalu lakukan penempatan via menu assign.</span>}
                    right={
                        <>
                            <button type="button" className="mcr-btn secondary" onClick={() => setRoomModalOpen(true)}>
                                <Plus size={14} />
                                Tambah Kamar
                            </button>
                            <button type="button" className="mcr-btn primary" onClick={() => setBuildingModalOpen(true)}>
                                <Plus size={14} />
                                Tambah Gedung
                            </button>
                        </>
                    }
                />

                {buildings.length === 0 ? (
                    <CrudCard>
                        <CrudEmptyState title="Belum ada gedung asrama" description="Tambahkan gedung pertama untuk mulai mengatur kamar." />
                    </CrudCard>
                ) : (
                    buildings.map((building) => (
                        <CrudCard
                            key={building.id}
                            title={building.name}
                            subtitle={building.description ?? 'Tanpa deskripsi'}
                            right={
                                <div className="mcr-action-group">
                                    <span className="mcr-dot-badge active">{building.rooms?.length ?? 0} kamar</span>
                                    <button type="button" className="mcr-icon-action danger" onClick={() => setDeleteBuildingTarget(building)} title="Hapus gedung">
                                        <Trash2 size={13} />
                                    </button>
                                </div>
                            }
                        >
                            {!building.rooms || building.rooms.length === 0 ? (
                                <CrudEmptyState title="Belum ada kamar" description="Tambahkan kamar untuk gedung ini." />
                            ) : (
                                <table className="mcr-table">
                                    <thead>
                                        <tr>
                                            <th>Kamar</th>
                                            <th>Lantai</th>
                                            <th>Kapasitas</th>
                                            <th>Terisi</th>
                                            <th>Musyrif</th>
                                            <th style={{ textAlign: 'right' }}>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {building.rooms.map((room) => (
                                            <tr key={room.id}>
                                                <td>{room.room_number}</td>
                                                <td>{room.floor ?? '-'}</td>
                                                <td>{room.capacity}</td>
                                                <td>
                                                    <span className="mcr-dot-badge alumni">{room.occupants_count ?? 0}</span>
                                                </td>
                                                <td>{room.musyrif?.user?.name ?? '-'}</td>
                                                <td>
                                                    <div className="mcr-action-group">
                                                        <button type="button" className="mcr-icon-action danger" onClick={() => setDeleteRoomTarget(room)} title="Hapus kamar">
                                                            <Trash2 size={13} />
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </CrudCard>
                    ))
                )}
            </div>

            <CrudModal open={buildingModalOpen} onClose={() => setBuildingModalOpen(false)} title="Tambah Gedung" subtitle="Isi informasi dasar gedung asrama.">
                <form onSubmit={submitBuilding}>
                    <div className="mcr-form-grid">
                        <div className="mcr-form-group full">
                            <label htmlFor="building-name">Nama Gedung</label>
                            <input id="building-name" className="mcr-input" value={buildingForm.data.name} onChange={(e) => buildingForm.setData('name', e.target.value)} />
                            <InputError message={buildingForm.errors.name} />
                        </div>
                        <div className="mcr-form-group full">
                            <label htmlFor="building-description">Deskripsi</label>
                            <textarea id="building-description" className="mcr-textarea" value={buildingForm.data.description} onChange={(e) => buildingForm.setData('description', e.target.value)} />
                            <InputError message={buildingForm.errors.description} />
                        </div>
                    </div>
                    <div style={{ marginTop: 12, display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
                        <button type="button" className="mcr-btn ghost" onClick={() => setBuildingModalOpen(false)}>Batal</button>
                        <button type="submit" className="mcr-btn primary" disabled={buildingForm.processing}>{buildingForm.processing ? 'Menyimpan...' : 'Simpan'}</button>
                    </div>
                </form>
            </CrudModal>

            <CrudModal open={roomModalOpen} onClose={() => setRoomModalOpen(false)} title="Tambah Kamar" subtitle="Pilih gedung lalu isi data kamar.">
                <form onSubmit={submitRoom}>
                    <div className="mcr-form-grid">
                        <div className="mcr-form-group full">
                            <label htmlFor="room-building">Gedung</label>
                            <select id="room-building" className="mcr-form-select" value={roomForm.data.building_id} onChange={(e) => roomForm.setData('building_id', e.target.value)}>
                                <option value="">Pilih gedung</option>
                                {buildings.map((b) => (
                                    <option key={b.id} value={String(b.id)}>{b.name}</option>
                                ))}
                            </select>
                            <InputError message={roomForm.errors.building_id} />
                        </div>
                        <div className="mcr-form-group">
                            <label htmlFor="room-number">Nomor Kamar</label>
                            <input id="room-number" className="mcr-input" value={roomForm.data.room_number} onChange={(e) => roomForm.setData('room_number', e.target.value)} />
                            <InputError message={roomForm.errors.room_number} />
                        </div>
                        <div className="mcr-form-group">
                            <label htmlFor="room-capacity">Kapasitas</label>
                            <input id="room-capacity" type="number" min={1} max={20} className="mcr-input" value={roomForm.data.capacity} onChange={(e) => roomForm.setData('capacity', e.target.value)} />
                            <InputError message={roomForm.errors.capacity} />
                        </div>
                        <div className="mcr-form-group">
                            <label htmlFor="room-floor">Lantai</label>
                            <input id="room-floor" type="number" min={1} className="mcr-input" value={roomForm.data.floor} onChange={(e) => roomForm.setData('floor', e.target.value)} />
                            <InputError message={roomForm.errors.floor} />
                        </div>
                    </div>
                    <div style={{ marginTop: 12, display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
                        <button type="button" className="mcr-btn ghost" onClick={() => setRoomModalOpen(false)}>Batal</button>
                        <button type="submit" className="mcr-btn primary" disabled={roomForm.processing}>{roomForm.processing ? 'Menyimpan...' : 'Simpan'}</button>
                    </div>
                </form>
            </CrudModal>

            <CrudConfirmModal
                open={deleteBuildingTarget !== null}
                onClose={() => setDeleteBuildingTarget(null)}
                onConfirm={deleteBuilding}
                title="Konfirmasi Hapus Gedung"
                description={`Hapus gedung "${deleteBuildingTarget?.name ?? '-'}"?`}
                confirmLabel="Hapus Gedung"
            />
            <CrudConfirmModal
                open={deleteRoomTarget !== null}
                onClose={() => setDeleteRoomTarget(null)}
                onConfirm={deleteRoom}
                title="Konfirmasi Hapus Kamar"
                description={`Hapus kamar "${deleteRoomTarget?.room_number ?? '-'}"?`}
                confirmLabel="Hapus Kamar"
            />
        </AppLayout>
    );
}
