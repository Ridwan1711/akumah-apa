type MemberListParams = {
    class_id?: number | string;
    room_id?: number | string;
    tingkat_sekolah_id?: number | string;
    academic_year_id?: number | string;
    status?: string;
};

/** URL daftar santri terfilter (anggota kelas / kobong / tingkat formal). */
export function memberListUrl(params: MemberListParams): string {
    const q = new URLSearchParams();
    if (params.class_id) {
        q.set('class_id', String(params.class_id));
    }
    if (params.room_id) {
        q.set('room_id', String(params.room_id));
    }
    if (params.tingkat_sekolah_id) {
        q.set('tingkat_sekolah_id', String(params.tingkat_sekolah_id));
    }
    if (params.academic_year_id) {
        q.set('academic_year_id', String(params.academic_year_id));
    }
    if (params.status) {
        q.set('status', params.status);
    }
    const query = q.toString();
    return query ? `/admin/students?${query}` : '/admin/students';
}
