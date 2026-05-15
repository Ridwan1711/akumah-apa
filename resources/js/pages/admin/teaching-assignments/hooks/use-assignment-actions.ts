import { router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { SelectOption } from '@/components/manhood';
import type { TeacherAssignment, User } from '@/types';
import { TEACHING_ASSIGNMENTS_ROUTE } from '../constants';
import type { ToastState } from '../types';

type UseAssignmentActionsParams = {
    teachers: Pick<User, 'id' | 'name'>[];
    semesters: Array<{ id: number; is_active?: boolean }>;
    selectedSemesterId: number;
    assignmentMap: Map<string, TeacherAssignment>;
};

export function useAssignmentActions({
    teachers,
    semesters,
    selectedSemesterId,
    assignmentMap,
}: UseAssignmentActionsParams) {
    const [selectedTeacherId, setSelectedTeacherId] = useState('');
    const [semesterId, setSemesterId] = useState(String(selectedSemesterId));
    const [busyKey, setBusyKey] = useState<string | null>(null);
    const [isRefreshing, setIsRefreshing] = useState(false);
    const [toast, setToast] = useState<ToastState>(null);
    const [isBulkAssigning, setIsBulkAssigning] = useState(false);

    const dragActiveRef = useRef(false);
    const dragVisitedRef = useRef<Set<string>>(new Set());
    const bulkQueueRef = useRef<Array<{ classId: number; subjectId: number }>>([]);
    const bulkProcessingRef = useRef(false);
    const bulkStatsRef = useRef({ success: 0, failed: 0, skipped: 0 });

    const defaultPeriodId = useMemo(() => {
        const active = semesters.find((p) => p.is_active);
        return String(active?.id ?? semesters[0]?.id ?? '');
    }, [semesters]);

    const teacherSelectOptions = useMemo<SelectOption[]>(
        () => teachers.map((t) => ({ value: t.id, label: t.name })),
        [teachers],
    );

    const selectedTeacherOption = useMemo(
        () => teacherSelectOptions.find((o) => String(o.value) === selectedTeacherId) ?? null,
        [teacherSelectOptions, selectedTeacherId],
    );

    const selectedTeacher = teachers.find((t) => String(t.id) === selectedTeacherId);

    useEffect(() => {
        if (toast) {
            const t = setTimeout(() => setToast(null), 4000);
            return () => clearTimeout(t);
        }
    }, [toast]);

    useEffect(() => {
        const endDrag = () => {
            dragActiveRef.current = false;
        };
        window.addEventListener('mouseup', endDrag);
        return () => window.removeEventListener('mouseup', endDrag);
    }, []);

    function refreshByPeriod(nextPeriodId: string) {
        setIsRefreshing(true);
        router.get(TEACHING_ASSIGNMENTS_ROUTE, { semester_id: nextPeriodId || defaultPeriodId }, {
            preserveScroll: true,
            onFinish: () => setIsRefreshing(false),
        });
    }

    function handleSemesterChange(value: string) {
        setSemesterId(value);
        refreshByPeriod(value);
    }

    function assignTeacher(classId: number, subjectId: number) {
        if (!selectedTeacherId) {
            setToast({ message: 'Pilih guru terlebih dahulu sebelum assign.', type: 'error' });
            return;
        }

        const key = `${subjectId}:${classId}`;
        setBusyKey(key);

        router.post(TEACHING_ASSIGNMENTS_ROUTE, {
            teacher_id: selectedTeacherId,
            class_id: classId,
            subject_id: subjectId,
            semester_id: semesterId || defaultPeriodId,
        }, {
            preserveScroll: true,
            onFinish: () => setBusyKey(null),
            onSuccess: () => setToast({ message: 'Berhasil menugaskan guru.', type: 'success' }),
            onError: () => setToast({ message: 'Gagal menugaskan guru.', type: 'error' }),
        });
    }

    function queueBulkAssign(classId: number, subjectId: number) {
        if (!selectedTeacherId) return;

        const key = `${subjectId}:${classId}`;
        if (dragVisitedRef.current.has(key)) return;
        dragVisitedRef.current.add(key);

        const current = assignmentMap.get(key);
        const selectedTeacherNum = Number(selectedTeacherId);
        if (current && current.teacher_id === selectedTeacherNum) {
            bulkStatsRef.current.skipped += 1;
            return;
        }

        bulkQueueRef.current.push({ classId, subjectId });
        processBulkQueue();
    }

    function processBulkQueue() {
        if (bulkProcessingRef.current) return;
        if (bulkQueueRef.current.length === 0) {
            if (isBulkAssigning) {
                const { success, failed, skipped } = bulkStatsRef.current;
                setIsBulkAssigning(false);
                const message = `Bulk assign selesai: ${success} berhasil, ${failed} gagal, ${skipped} dilewati.`;
                setToast({ message, type: failed > 0 ? 'error' : 'success' });
            }
            return;
        }

        const item = bulkQueueRef.current.shift()!;
        bulkProcessingRef.current = true;
        setBusyKey(`${item.subjectId}:${item.classId}`);

        router.post(TEACHING_ASSIGNMENTS_ROUTE, {
            teacher_id: selectedTeacherId,
            class_id: item.classId,
            subject_id: item.subjectId,
            semester_id: semesterId || defaultPeriodId,
        }, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            onSuccess: () => {
                bulkStatsRef.current.success += 1;
            },
            onError: () => {
                bulkStatsRef.current.failed += 1;
            },
            onFinish: () => {
                setBusyKey(null);
                bulkProcessingRef.current = false;
                processBulkQueue();
            },
        });
    }

    function handleDragStartCell(classId: number, subjectId: number) {
        if (!selectedTeacherId) return;
        if (isBulkAssigning) return;
        dragActiveRef.current = true;
        setIsBulkAssigning(true);
        dragVisitedRef.current = new Set();
        bulkStatsRef.current = { success: 0, failed: 0, skipped: 0 };
        queueBulkAssign(classId, subjectId);
    }

    function handleDragEnterCell(classId: number, subjectId: number) {
        if (!dragActiveRef.current || !selectedTeacherId || isBulkAssigning === false) return;
        queueBulkAssign(classId, subjectId);
    }

    function handleDragEnd() {
        dragActiveRef.current = false;
        if (!isBulkAssigning) return;
        processBulkQueue();
    }

    function removeAssignment(assignment: TeacherAssignment) {
        if (!confirm(`Hapus penugasan ${assignment.teacher?.name} dari mata pelajaran ini?`)) return;

        const key = `${assignment.subject_id}:${assignment.class_id}`;
        setBusyKey(key);

        router.delete(`${TEACHING_ASSIGNMENTS_ROUTE}/${assignment.id}`, {
            data: { semester_id: semesterId || defaultPeriodId },
            preserveScroll: true,
            onFinish: () => setBusyKey(null),
            onSuccess: () => setToast({ message: 'Penugasan berhasil dihapus.', type: 'success' }),
            onError: () => setToast({ message: 'Gagal menghapus penugasan.', type: 'error' }),
        });
    }

    return {
        selectedTeacherId,
        setSelectedTeacherId,
        semesterId,
        defaultPeriodId,
        busyKey,
        isRefreshing,
        toast,
        setToast,
        isBulkAssigning,
        teacherSelectOptions,
        selectedTeacherOption,
        selectedTeacher,
        handleSemesterChange,
        assignTeacher,
        removeAssignment,
        handleDragStartCell,
        handleDragEnterCell,
        handleDragEnd,
    };
}
