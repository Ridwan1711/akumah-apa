import { Head, Link, useForm } from '@inertiajs/react';
import { BookOpenCheck, Columns3, GraduationCap, Users } from 'lucide-react';
import { useMemo, useRef } from 'react';
import FlashMessage from '@/components/flash-message';
import {
    CrudCard,
    CrudPageHeader,
    CrudStatStrip,
    CrudTableShell,
} from '@/components/manhood';
import AppLayout from '@/layouts/app-layout';
import type {
    AssessmentComponent,
    BreadcrumbItem,
    SchoolClass,
    Student,
    Subject,
} from '@/types';

type GradeRow = {
    student_id: number;
    components: Record<string, number>;
};

type Props = {
    academicPeriod: { id: number; name: string };
    subject: Pick<Subject, 'id' | 'name'>;
    schoolClass: Pick<SchoolClass, 'id' | 'name' | 'grade_level_id'>;
    assessmentComponents: Pick<AssessmentComponent, 'id' | 'name' | 'type' | 'is_core_required'>[];
    defaultActiveComponentIds: number[];
    students: Pick<Student, 'id' | 'nis' | 'full_name'>[];
    gradeMatrix: Record<number, Record<number, number>>;
    isGuru?: boolean;
};

export default function KitabGradeInput({
    academicPeriod,
    subject,
    schoolClass,
    assessmentComponents,
    defaultActiveComponentIds,
    students,
    gradeMatrix,
    isGuru,
}: Props) {
    const subjectUrl = `/admin/kitab-grades/${academicPeriod.id}`;
    const classUrl = `/admin/kitab-grades/${academicPeriod.id}/${subject.id}`;
    const inputUrl = `/admin/kitab-grades/${academicPeriod.id}/${subject.id}/${schoolClass.id}`;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Nilai Diniyyah', href: '/admin/kitab-grades' },
        { title: academicPeriod.name, href: subjectUrl },
        { title: subject.name, href: classUrl },
        { title: schoolClass.name, href: inputUrl },
    ];

    const initialGrades: GradeRow[] = useMemo(() => {
        return students.map((student) => {
            const components: Record<string, number> = {};
            assessmentComponents.forEach((component) => {
                const existing = gradeMatrix[student.id]?.[component.id] ?? 0;
                components[String(component.id)] = Number(existing);
            });
            return {
                student_id: student.id,
                components,
            };
        });
    }, [students, assessmentComponents, gradeMatrix]);

    const { data, setData, processing, post } = useForm({
        grades: initialGrades,
        active_component_ids: defaultActiveComponentIds,
    });
    const activeComponentIds = useMemo(
        () => data.active_component_ids.map((id) => Number(id)).filter((id) => Number.isInteger(id)),
        [data.active_component_ids],
    );
    const activeComponents = useMemo(
        () => assessmentComponents.filter((component) => activeComponentIds.includes(component.id)),
        [assessmentComponents, activeComponentIds],
    );
    const inputRefs = useRef<Array<Array<HTMLInputElement | null>>>([]);

    const ranked = useMemo(() => {
        return students
            .map((student, studentIndex) => {
                const row = data.grades[studentIndex];
                const values = activeComponents.map((component) => Number(row?.components[String(component.id)] ?? 0));
                const average =
                    values.length > 0
                        ? values.reduce((carry, value) => carry + value, 0) / values.length
                        : 0;
                return {
                    studentId: student.id,
                    average,
                };
            })
            .sort((a, b) => b.average - a.average)
            .map((item, index) => ({ ...item, rank: index + 1 }));
    }, [students, data.grades, activeComponents]);

    const rankMap = useMemo(() => {
        const map = new Map<number, number>();
        ranked.forEach((item) => map.set(item.studentId, item.rank));
        return map;
    }, [ranked]);

    const overallAverage = useMemo(() => {
        if (students.length === 0) return 0;
        const sum = ranked.reduce((carry, item) => carry + item.average, 0);
        return sum / students.length;
    }, [ranked, students.length]);

    function updateScore(rowIndex: number, componentId: number, value: string) {
        const next = [...data.grades];
        const parsed = Math.min(100, Math.max(0, Number.parseInt(value, 10) || 0));
        const row = next[rowIndex];
        if (!row) return;
        row.components = {
            ...row.components,
            [String(componentId)]: parsed,
        };
        next[rowIndex] = row;
        setData('grades', next);
    }

    function focusCell(row: number, col: number) {
        inputRefs.current[row]?.[col]?.focus();
        inputRefs.current[row]?.[col]?.select();
    }

    function onCellKeyDown(
        event: React.KeyboardEvent<HTMLInputElement>,
        rowIndex: number,
        colIndex: number,
    ) {
        if (event.key === 'ArrowUp') {
            event.preventDefault();
            if (rowIndex > 0) focusCell(rowIndex - 1, colIndex);
            return;
        }
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            if (rowIndex < students.length - 1) focusCell(rowIndex + 1, colIndex);
            return;
        }
        if (event.key === 'ArrowLeft') {
            event.preventDefault();
            if (colIndex > 0) focusCell(rowIndex, colIndex - 1);
            return;
        }
        if (event.key === 'ArrowRight') {
            event.preventDefault();
            if (colIndex < activeComponents.length - 1) focusCell(rowIndex, colIndex + 1);
            return;
        }
    }

    function handleSave(e: React.FormEvent) {
        e.preventDefault();
        post(inputUrl);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Nilai — ${subject.name}`} />
            <div>
                <CrudPageHeader
                    title="Input Nilai Multi Komponen"
                    description={isGuru ? 'Format matrix untuk input cepat ala spreadsheet.' : 'Input nilai per siswa dan komponen dalam satu tabel.'}
                />

                <CrudStatStrip
                    items={[
                        { key: 'students', label: 'Jumlah Siswa', value: students.length, icon: <Users size={18} />, tone: 'blue' },
                        { key: 'components', label: 'Komponen', value: assessmentComponents.length, icon: <Columns3 size={18} />, tone: 'green' },
                        { key: 'avg', label: 'Rata-rata Kelas', value: overallAverage.toFixed(2), icon: <BookOpenCheck size={18} />, tone: 'amber' },
                        { key: 'class', label: 'Kelas', value: schoolClass.name, icon: <GraduationCap size={18} />, tone: 'purple' },
                    ]}
                />

                <FlashMessage />

                <div style={{ display: 'flex', gap: 8, marginBottom: 12 }}>
                    <Link href={classUrl} className="mcr-btn ghost">Ganti Kelas</Link>
                    <Link href={subjectUrl} className="mcr-btn ghost">Ganti Pelajaran</Link>
                </div>

                <CrudCard
                    title="Matrix Nilai"
                    subtitle="Komponen nilai sudah dikunci di langkah setting. Gunakan Arrow Up/Down/Left/Right untuk berpindah antar cell input."
                >
                    <form onSubmit={handleSave}>
                        <CrudTableShell>
                            <table className="mcr-table">
                                <thead>
                                    <tr>
                                        <th style={{ width: 56 }}>No</th>
                                        <th style={{ minWidth: 220 }}>Nama</th>
                                        {activeComponents.map((component) => (
                                            <th key={component.id} style={{ minWidth: 130, textAlign: 'center' }}>
                                                {component.name}
                                            </th>
                                        ))}
                                        <th style={{ minWidth: 120, textAlign: 'center' }}>Rata-rata</th>
                                        <th style={{ minWidth: 90, textAlign: 'center' }}>Rank</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {activeComponents.length === 0 ? (
                                        <tr>
                                            <td colSpan={5} style={{ textAlign: 'center', padding: 18 }}>
                                                Aktifkan minimal satu komponen untuk mengisi nilai.
                                            </td>
                                        </tr>
                                    ) : null}
                                    {activeComponents.length > 0 && students.map((student, rowIndex) => {
                                        const row = data.grades[rowIndex];
                                        const componentValues = activeComponents.map((component) =>
                                            Number(row?.components[String(component.id)] ?? 0),
                                        );
                                        const avg = componentValues.length
                                            ? componentValues.reduce((sum, value) => sum + value, 0) / componentValues.length
                                            : 0;

                                        return (
                                            <tr key={student.id}>
                                                <td>{rowIndex + 1}</td>
                                                <td>
                                                    <div style={{ fontWeight: 600 }}>{student.full_name}</div>
                                                    <div className="mcr-table-meta">{student.nis}</div>
                                                </td>
                                                {activeComponents.map((component, colIndex) => (
                                                    <td key={component.id} style={{ textAlign: 'center' }}>
                                                        <input
                                                            ref={(el) => {
                                                                if (!inputRefs.current[rowIndex]) inputRefs.current[rowIndex] = [];
                                                                inputRefs.current[rowIndex][colIndex] = el;
                                                            }}
                                                            type="number"
                                                            min={0}
                                                            max={100}
                                                            value={row?.components[String(component.id)] ?? 0}
                                                            onChange={(e) => updateScore(rowIndex, component.id, e.target.value)}
                                                            onKeyDown={(e) => onCellKeyDown(e, rowIndex, colIndex)}
                                                            className="mcr-input"
                                                            style={{ width: 82, textAlign: 'center', fontWeight: 700 }}
                                                        />
                                                    </td>
                                                ))}
                                                <td style={{ textAlign: 'center', fontWeight: 700 }}>{avg.toFixed(2)}</td>
                                                <td style={{ textAlign: 'center' }}>
                                                    <span className="mcr-dot-badge active">#{rankMap.get(student.id) ?? '-'}</span>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </CrudTableShell>

                        <div style={{ marginTop: 12, display: 'flex', justifyContent: 'flex-end' }}>
                            <button type="submit" className="mcr-btn primary" disabled={processing || activeComponents.length === 0}>
                                {processing ? 'Menyimpan...' : 'Simpan Semua Nilai'}
                            </button>
                        </div>
                    </form>
                </CrudCard>
            </div>
        </AppLayout>
    );
}
