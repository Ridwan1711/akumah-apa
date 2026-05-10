import type { User } from './auth';

export type AcademicYear = {
    id: number;
    name: string;
    start_date: string;
    end_date: string;
    is_active?: boolean;
    semesters?: Semester[];
    created_at: string;
    updated_at: string;
};

export type Semester = {
    id: number;
    academic_year_id: number;
    name: string;
    start_date: string;
    end_date: string;
    is_active?: boolean;
    academic_year?: AcademicYear;
    created_at: string;
    updated_at: string;
};

/** Wali kelas per periode (`class_walis`). */
export type ClassWali = {
    id: number;
    class_id: number;
    teacher_id: number;
    period_id: number;
    teacher?: { id: number; name: string };
};

/** Diniyyah `classes` row (model `SchoolClass`). */
export type SchoolClass = {
    id: number;
    name: string;
    /** @deprecated Legacy level tag (e.g. ibtida, 1salafy). */
    level?: string | null;
    /** Santri di kelas ini: L = Santriyyin, P = Santriyah (satu kelas = satu jenis). */
    student_gender?: 'L' | 'P' | null;
    order?: number;
    student_gender_label?: string | null;
    grade_level_id?: number;
    /** @deprecated Use `order`. */
    level_order?: number;
    grade_level?: { id: number; name: string; order: number };
    students_count?: number;
    /** Eager-loaded untuk periode akademik aktif saja (admin kelas diniyah). */
    walis?: ClassWali[];
    created_at?: string;
    updated_at?: string;
};

export type GradeLevel = {
    id: number;
    name: string;
    order: number;
    created_at?: string;
    updated_at?: string;
};

/** @deprecated Use `SchoolClass` — kept for gradual migration of imports. */
export type DiniyahClass = SchoolClass;

export type EmProfile = {
    id: number;
    student_id: number;
    nisn: string | null;
    nism: string | null;
    kewarganegaraan: string | null;
    anak_ke: string | null;
    jumlah_saudara: string | null;
    agama: string | null;
    cita_cita: string | null;
    no_hp: string | null;
    email: string | null;
    hobi: string | null;
    pendidikan_sebelumnya: string | null;
    sumber_pembiayaan: string | null;
    kebutuhan_khusus: string | null;
    no_kip: string | null;
    no_kk: string | null;
    nama_kepala_keluarga: string | null;
    tanpa_handphone: boolean;
    status_mukim: string | null;
    status_tempat_tinggal: string | null;
    jarak_tempat_tinggal_lembaga: string | null;
    transportasi_ke_lembaga: string | null;
    asal_daerah: string | null;
    catatan_khusus: string | null;
    santri_provinsi: string | null;
    santri_kabupaten: string | null;
    santri_kecamatan: string | null;
    santri_kelurahan: string | null;
    santri_dusun: string | null;
    santri_rt: string | null;
    santri_rw: string | null;
    santri_alamat: string | null;
    santri_kode_pos: string | null;
    created_at: string;
    updated_at: string;
};

export type Student = {
    id: number;
    user_id: number | null;
    nis: string;
    nik: string | null;
    full_name: string;
    birth_place: string | null;
    birth_date: string | null;
    gender: 'L' | 'P';
    sex?: 'L' | 'P';
    photo: string | null;
    address: string | null;
    address_line?: string | null;
    status: 'active' | 'alumni' | 'keluar' | 'wafat';
    is_kuliah?: boolean;
    admission_year: number;
    current_class_id: number | null;
    current_class?: SchoolClass;
    guardians?: Guardian[];
    emis_profile?: EmProfile;
    em_profile?: {
        santri?: Record<string, unknown>;
        alamat?: Record<string, unknown>;
    };
    ppdb_application_id?: number | null;
    ppdb_reg_no?: string | null;
    ppdb_synced_at?: string | null;
    user?: User;
    violation_summary?: ViolationSummary;
    created_at: string;
    updated_at: string;
};

export type StudentPosition = {
    id: number;
    student_id: number;
    position_type: string;
    division_code: string | null;
    is_active: boolean;
    started_at: string | null;
    ended_at: string | null;
    student?: Pick<Student, 'id' | 'full_name' | 'nis' | 'current_class'>;
    created_at: string;
    updated_at: string;
};

export type Guardian = {
    id: number;
    user_id: number | null;
    student_id: number;
    full_name: string;
    nik: string | null;
    phone: string | null;
    email: string | null;
    occupation: string | null;
    income_band: string | null;
    relationship: string | null;
    status?: string | null;
    birth_place?: string | null;
    birth_date?: string | null;
    without_phone?: boolean;
    last_education?: string | null;
    education?: string | null;
    monthly_income?: string | null;
    kewarganegaraan?: string | null;
    alamat?: string | null;
    address_line?: string | null;
    is_alive?: boolean;
    pivot?: {
        relationship: string;
    };
    student?: Student;
    user?: User;
    created_at: string;
    updated_at: string;
};

export type GeneratedAccount = {
    nis?: string;
    name?: string;
    guardian_name?: string;
    student_nis?: string;
    username: string;
    password: string;
};

/** Response from POST /admin/account-generator/wali-preview */
export type WaliPreviewStudentInSelection = {
    id: number;
    full_name: string;
    nis: string | null;
    relationship: string | null;
};

export type WaliPreviewGuardianRow = {
    id: number;
    full_name: string;
    relationship: string | null;
    already_has_account: boolean;
    is_shared_in_selection: boolean;
    students_in_selection: WaliPreviewStudentInSelection[];
    total_children_in_db: number;
};

export type WaliPreviewResponse = {
    selection_count: number;
    guardians: WaliPreviewGuardianRow[];
    students_without_guardians: Pick<Student, 'id' | 'full_name' | 'nis'>[];
};

// --- Akademik Diniyyah ---

export type Subject = {
    id: number;
    name: string;
};

/** @deprecated Use `Subject` */
export type KitabSubject = Subject;

export type AcademicPeriod = {
    id: number;
    academic_year_id: number;
    semester_id: number;
    is_active?: boolean;
    academic_year?: Pick<AcademicYear, 'id' | 'name'>;
    semester?: Pick<Semester, 'id' | 'name'>;
    /** @deprecated legacy flattened label. */
    name?: string;
    /** @deprecated legacy field. */
    type?: string;
};

export type AssessmentComponent = {
    id: number;
    name: string;
    type: string;
    weight?: number | string | null;
    is_core_required?: boolean;
    created_at?: string;
    updated_at?: string;
};

export type TeacherAssignment = {
    id: number;
    teacher_id: number;
    class_id: number;
    subject_id: number;
    period_id: number;
    target_jam: number;
    teacher?: Pick<User, 'id' | 'name'>;
    school_class?: Pick<SchoolClass, 'id' | 'name'>;
    subject?: Subject;
    period?: Pick<AcademicPeriod, 'id' | 'academic_year_id' | 'semester_id' | 'is_active'> & {
        academic_year?: Pick<AcademicYear, 'id' | 'name'>;
        semester?: Pick<Semester, 'id' | 'name'>;
    };
    created_at?: string;
    updated_at?: string;
};

/** @deprecated Use `TeacherAssignment` */
export type KitabTeachingAssignment = TeacherAssignment;

export type ScheduleSet = {
    id: number;
    period_id: number;
    name: string;
    jam_count: number;
    day_count: number;
    is_active: boolean;
    created_by?: number | null;
    cells_count?: number;
    unmet_pengampu_count?: number;
    unmet_jam_total?: number;
    period?: Pick<AcademicPeriod, 'id' | 'academic_year_id' | 'semester_id' | 'is_active'> & {
        academic_year?: Pick<AcademicYear, 'id' | 'name'>;
        semester?: Pick<Semester, 'id' | 'name'>;
    };
    creator?: Pick<User, 'id' | 'name'>;
    created_at?: string;
    updated_at?: string;
};

export type ScheduleTimeSlot = {
    id?: number;
    jam_no: number;
    time_start: string;
    time_end: string;
};

export type ScheduleMatrixCell = {
    schedule_id: number;
    class_id: number;
    day: number;
    jam_no: number;
    teacher_id: number;
    teacher_name?: string | null;
    subject_id: number;
    subject_name?: string | null;
    combined_group_id: string | null;
};

export type ScheduleMatrixPayload = {
    classes: Array<Pick<SchoolClass, 'id' | 'name' | 'grade_level_id' | 'order'>>;
    slots: ScheduleTimeSlot[];
    days: number[];
    cells: Record<string, ScheduleMatrixCell>;
};

export type ScheduleMatrixPengampu = {
    id: number;
    teacher_id: number;
    class_id: number;
    subject_id: number;
    target_jam: number;
    target_jam_effective?: number;
    teacher?: Pick<User, 'id' | 'name'>;
    school_class?: Pick<SchoolClass, 'id' | 'name' | 'grade_level_id' | 'order'>;
    subject?: Pick<Subject, 'id' | 'name'>;
};

export type ScheduleConflictType =
    | 'none'
    | 'occupied'
    | 'same_subject_other_class'
    | 'different_subject_other_class'
    | 'target_reached';

export type ScheduleConflictResponse = {
    type: ScheduleConflictType;
    allocation?: number;
    target_jam?: number;
    cell?: {
        schedule_id: number;
        teacher_id: number;
        subject_id: number;
    };
    conflicts?: Array<{
        schedule_id: number;
        class_id: number;
        class_name?: string;
        subject_id: number;
        subject_name?: string;
        combined_group_id?: string | null;
    }>;
};

export type DiniyyahScore = {
    id: number;
    student_id: number;
    subject_id: number;
    component_id: number;
    period_id: number;
    score: number | string | null;
    status?: string;
    grade_letter?: string | null;
    subject?: Subject;
    created_at?: string;
    updated_at?: string;
};

/** @deprecated Use `DiniyyahScore` */
export type KitabGrade = DiniyyahScore;

// --- Asrama ---

export type DormBuilding = {
    id: number;
    name: string;
    description: string | null;
    rooms?: DormRoom[];
    created_at: string;
    updated_at: string;
};

export type DormRoom = {
    id: number;
    building_id: number;
    room_number: string;
    capacity: number;
    floor: number | null;
    occupants_count?: number;
    building?: DormBuilding;
    musyrif?: MusyrifAssignment;
    created_at: string;
    updated_at: string;
};

export type DormAssignment = {
    id: number;
    student_id: number;
    room_id: number;
    academic_year_id: number;
    checkin_date: string;
    checkout_date: string | null;
    student?: Student;
    room?: DormRoom;
    academic_year?: AcademicYear;
    created_at: string;
    updated_at: string;
};

export type MusyrifAssignment = {
    id: number;
    user_id: number;
    assigned_room_id: number | null;
    user?: User;
};

// --- Pelanggaran ---

export type ViolationType = {
    id: number;
    name: string;
    points: number;
    category: 'ringan' | 'sedang' | 'berat';
    created_at: string;
    updated_at: string;
};

export type StudentViolation = {
    id: number;
    student_id: number;
    violation_type_id: number;
    date: string;
    description: string | null;
    handled_by: number | null;
    status: 'open' | 'resolved';
    resolution_notes: string | null;
    student?: Student;
    violation_type?: ViolationType;
    handler?: User;
    created_at: string;
    updated_at: string;
};

export type ViolationSummary = {
    id: number;
    student_id: number;
    total_points: number;
    last_violation_date: string | null;
};

// --- Perizinan ---

export type LeavePermission = {
    id: number;
    student_id: number;
    reason: string;
    leave_date: string;
    return_date: string | null;
    actual_return_date: string | null;
    approved_by: number | null;
    status: 'pending' | 'approved' | 'rejected';
    rejection_reason: string | null;
    student?: Student;
    approver?: User;
    created_at: string;
    updated_at: string;
};

// --- Report Card ---

export type ReportCard = {
    id: number;
    student_id: number;
    semester_id: number;
    wali_kelas_notes: string | null;
    generated_by: number | null;
    generated_at: string | null;
    verification_token: string | null;
    student?: Student;
    semester?: Semester;
    created_at: string;
    updated_at: string;
};

// --- Keuangan ---

export type PaymentType = {
    id: number;
    name: string;
    code: string;
    category: 'spp' | 'non_spp' | 'infaq';
    is_recurring: boolean;
    default_amount: number;
    kuliah_amount?: number | null;
    default_breakdown?: Array<{ label: string; amount: number }> | null;
    description: string | null;
    is_active: boolean;
    created_at: string;
    updated_at: string;
};

export type StudentDiscount = {
    id: number;
    student_id: number;
    payment_type_id: number;
    academic_year_id: number;
    discount_type: 'percentage' | 'fixed';
    discount_value: number;
    reason: string | null;
    approved_by: number | null;
    student?: Student;
    payment_type?: PaymentType;
    academic_year?: AcademicYear;
    approver?: import('./auth').User;
    created_at: string;
    updated_at: string;
};

export type Invoice = {
    id: number;
    invoice_number: string;
    student_id: number;
    payment_type_id: number;
    academic_year_id: number;
    semester_id: number | null;
    month: number | null;
    amount: number;
    discount_amount: number;
    final_amount: number;
    breakdown?: Array<{ label: string; amount: number }> | null;
    breakdown_items?: Array<{ label: string; amount: number }> | null;
    status: 'pending' | 'paid' | 'partial' | 'overdue' | 'cancelled';
    due_date: string;
    notes: string | null;
    generated_by: number | null;
    total_paid?: number;
    pending_amount?: number;
    remaining?: number;
    payments_count?: number;
    student?: Student;
    payment_type?: PaymentType;
    academic_year?: AcademicYear;
    semester?: Semester;
    payments?: Payment[];
    created_at: string;
    updated_at: string;
};

export type StudentInvoiceGroup = {
    student_id: number;
    student_nis: string;
    student_name: string;
    class_name: string | null;
    invoice_count: number;
    total_amount: number;
    total_paid: number;
    pending_amount: number;
    total_remaining: number;
    invoices: Invoice[];
};

export type Payment = {
    id: number;
    payment_number: string;
    invoice_id: number;
    amount: number;
    payment_method: 'cash' | 'bank_transfer' | 'gateway';
    payment_date: string;
    proof_file: string | null;
    gateway_order_id: string | null;
    gateway_transaction_id: string | null;
    gateway_payment_type: string | null;
    gateway_va_number?: string | null;
    gateway_qr_url?: string | null;
    gateway_expiry_time?: string | null;
    status: 'pending' | 'verified' | 'rejected';
    verified_by: number | null;
    verified_at: string | null;
    notes: string | null;
    invoice?: Invoice;
    verifier?: import('./auth').User;
    created_at: string;
    updated_at: string;
};

// --- Audit Log ---

export type AuditLog = {
    id: number;
    user_id: number | null;
    module: string;
    action: string;
    auditable_type: string;
    auditable_id: number;
    old_data: Record<string, unknown> | null;
    new_data: Record<string, unknown> | null;
    ip_address: string | null;
    user_agent: string | null;
    user?: User;
    created_at: string;
    /** Ringkasan bahasa sehari-hari (dari server) */
    summary_line?: string;
    time_relative?: string;
    actor_label?: string;
    technical_target?: string;
};

export type SystemLog = {
    id: number;
    channel: string;
    level: string;
    message: string;
    context?: Record<string, unknown> | null;
    extra?: Record<string, unknown> | null;
    user_id?: number | null;
    user?: Pick<User, 'id' | 'name'> | null;
    ip_address?: string | null;
    method?: string | null;
    url?: string | null;
    trace?: string | null;
    logged_at: string;
    created_at: string;
    updated_at: string;
};

// --- Pagination ---

export type PaginatedData<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type ImportRun = {
    id: number;
    uuid: string;
    type: 'students' | 'teachers' | 'enrollments' | 'bulk' | 'invoices';
    job_type?: string | null;
    strategy: 'skip' | 'update';
    status: 'queued' | 'processing' | 'completed' | 'failed' | 'cancelled';
    requested_by: number | null;
    requestedBy?: {
        id: number;
        name: string;
    } | null;
    file_name: string;
    file_path: string;
    total_rows: number;
    processed_rows: number;
    created_count: number;
    updated_count: number;
    skipped_count: number;
    failed_count: number;
    error_report_path: string | null;
    error_message: string | null;
    started_at: string | null;
    finished_at: string | null;
    meta?: Record<string, unknown> | null;
    result_payload?: Record<string, unknown> | null;
    created_at: string;
    updated_at: string;
};
