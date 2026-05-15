import { useMemo, useState } from 'react';
import type { MultiValue } from 'react-select';
import type { SelectOption } from '@/components/manhood';
import type { SchoolClass, Subject, TeacherAssignment } from '@/types';
import type { GradeSubjectRow, TeachingAssignmentPageProps } from '../types';

type UseMatrixFiltersParams = Pick<
    TeachingAssignmentPageProps,
    'assignments' | 'classes' | 'gradeLevels' | 'subjects' | 'gradeSubjects'
>;

export function useMatrixFilters({
    assignments,
    classes,
    gradeLevels,
    subjects,
    gradeSubjects,
}: UseMatrixFiltersParams) {
    const [searchSubject, setSearchSubject] = useState('');
    const [searchClass, setSearchClass] = useState('');
    const [selectedGradeLevelIds, setSelectedGradeLevelIds] = useState<string[]>([]);
    const [selectedSubjectIds, setSelectedSubjectIds] = useState<string[]>([]);

    const gradeLevelOptions = useMemo<SelectOption[]>(
        () => gradeLevels.map((level) => ({ value: String(level.id), label: level.name })),
        [gradeLevels],
    );

    const subjectOptions = useMemo<SelectOption[]>(
        () => subjects.map((s) => ({ value: String(s.id), label: s.name })),
        [subjects],
    );

    const subjectsByLevelId = useMemo(() => {
        const map = new Map<string, Set<string>>();
        for (const row of gradeSubjects) {
            const levelId = String(row.grade_level_id);
            if (!map.has(levelId)) {
                map.set(levelId, new Set());
            }
            map.get(levelId)!.add(String(row.subject_id));
        }
        return map;
    }, [gradeSubjects]);

    const levelsBySubjectId = useMemo(() => {
        const map = new Map<string, Set<string>>();
        for (const row of gradeSubjects) {
            const subjectId = String(row.subject_id);
            if (!map.has(subjectId)) {
                map.set(subjectId, new Set());
            }
            map.get(subjectId)!.add(String(row.grade_level_id));
        }
        return map;
    }, [gradeSubjects]);

    const cascadingSubjectOptions = useMemo(() => {
        if (selectedGradeLevelIds.length === 0) {
            return subjectOptions;
        }
        const allowed = new Set<string>();
        for (const levelId of selectedGradeLevelIds) {
            subjectsByLevelId.get(levelId)?.forEach((subjectId) => allowed.add(subjectId));
        }
        return subjectOptions.filter((opt) => allowed.has(String(opt.value)));
    }, [subjectOptions, selectedGradeLevelIds, subjectsByLevelId]);

    const cascadingGradeLevelOptions = useMemo(() => {
        if (selectedSubjectIds.length === 0) {
            return gradeLevelOptions;
        }
        const allowed = new Set<string>();
        for (const subjectId of selectedSubjectIds) {
            levelsBySubjectId.get(subjectId)?.forEach((levelId) => allowed.add(levelId));
        }
        return gradeLevelOptions.filter((opt) => allowed.has(String(opt.value)));
    }, [gradeLevelOptions, selectedSubjectIds, levelsBySubjectId]);

    const hasMatrixScopeFilter =
        selectedGradeLevelIds.length > 0 || selectedSubjectIds.length > 0;

    const mappedPairSet = useMemo(
        () => new Set(gradeSubjects.map((item) => `${item.grade_level_id}:${item.subject_id}`)),
        [gradeSubjects],
    );

    const matrixClasses = useMemo(() => {
        let list = classes;

        if (selectedGradeLevelIds.length > 0) {
            const idSet = new Set(selectedGradeLevelIds);
            list = list.filter(
                (c) => c.grade_level_id != null && idSet.has(String(c.grade_level_id)),
            );
        }

        if (selectedSubjectIds.length > 0) {
            const allowedLevelIds = new Set<string>();
            for (const subjectId of selectedSubjectIds) {
                levelsBySubjectId.get(subjectId)?.forEach((levelId) => {
                    allowedLevelIds.add(levelId);
                });
            }
            list = list.filter(
                (c) =>
                    c.grade_level_id != null &&
                    allowedLevelIds.has(String(c.grade_level_id)),
            );
        }

        if (searchClass) {
            const q = searchClass.toLowerCase();
            list = list.filter((c) => c.name.toLowerCase().includes(q));
        }

        return list;
    }, [classes, searchClass, selectedGradeLevelIds, selectedSubjectIds, levelsBySubjectId]);

    const matrixSubjects = useMemo(() => {
        let list = subjects;

        if (selectedGradeLevelIds.length > 0) {
            const allowed = new Set<string>();
            for (const levelId of selectedGradeLevelIds) {
                subjectsByLevelId.get(levelId)?.forEach((subjectId) => {
                    allowed.add(subjectId);
                });
            }
            list = list.filter((s) => allowed.has(String(s.id)));
        }

        if (selectedSubjectIds.length > 0) {
            const idSet = new Set(selectedSubjectIds);
            list = list.filter((s) => idSet.has(String(s.id)));
        }

        if (searchSubject) {
            const q = searchSubject.toLowerCase();
            list = list.filter((s) => s.name.toLowerCase().includes(q));
        }

        return list;
    }, [subjects, selectedGradeLevelIds, selectedSubjectIds, searchSubject, subjectsByLevelId]);

    const matrixDisplaySubjects = useMemo(() => {
        if (!hasMatrixScopeFilter) {
            return matrixSubjects;
        }
        return matrixSubjects.filter((subject) =>
            matrixClasses.some(
                (cls) =>
                    cls.grade_level_id != null &&
                    mappedPairSet.has(`${cls.grade_level_id}:${subject.id}`),
            ),
        );
    }, [hasMatrixScopeFilter, matrixSubjects, matrixClasses, mappedPairSet]);

    const matrixDisplayClasses = useMemo(() => {
        if (!hasMatrixScopeFilter) {
            return matrixClasses;
        }
        return matrixClasses.filter((cls) =>
            cls.grade_level_id != null &&
            matrixSubjects.some((subject) =>
                mappedPairSet.has(`${cls.grade_level_id}:${subject.id}`),
            ),
        );
    }, [hasMatrixScopeFilter, matrixClasses, matrixSubjects, mappedPairSet]);

    const assignmentMap = useMemo(() => {
        const map = new Map<string, TeacherAssignment>();
        assignments.forEach((a) => map.set(`${a.subject_id}:${a.class_id}`, a));
        return map;
    }, [assignments]);

    function handleGradeLevelFilterChange(items: MultiValue<SelectOption>) {
        const nextLevelIds = items.map((item) => String(item.value));
        setSelectedGradeLevelIds(nextLevelIds);

        if (nextLevelIds.length === 0) {
            return;
        }

        const allowedSubjects = new Set<string>();
        for (const levelId of nextLevelIds) {
            subjectsByLevelId.get(levelId)?.forEach((subjectId) => {
                allowedSubjects.add(subjectId);
            });
        }

        setSelectedSubjectIds((prev) => prev.filter((id) => allowedSubjects.has(id)));
    }

    function handleSubjectFilterChange(items: MultiValue<SelectOption>) {
        const nextSubjectIds = items.map((item) => String(item.value));
        setSelectedSubjectIds(nextSubjectIds);

        if (nextSubjectIds.length === 0) {
            return;
        }

        const allowedLevels = new Set<string>();
        for (const subjectId of nextSubjectIds) {
            levelsBySubjectId.get(subjectId)?.forEach((levelId) => {
                allowedLevels.add(levelId);
            });
        }

        setSelectedGradeLevelIds((prev) => prev.filter((id) => allowedLevels.has(id)));
    }

    function applySubjectsFromMappingPreset() {
        const ids = new Set<string>();
        if (selectedGradeLevelIds.length > 0) {
            const levelSet = new Set(selectedGradeLevelIds);
            for (const row of gradeSubjects) {
                if (levelSet.has(String(row.grade_level_id))) {
                    ids.add(String(row.subject_id));
                }
            }
        } else {
            for (const row of gradeSubjects) {
                ids.add(String(row.subject_id));
            }
        }
        setSelectedSubjectIds(Array.from(ids));
    }

    const hasActiveFilters =
        Boolean(searchSubject || searchClass || selectedGradeLevelIds.length > 0 || selectedSubjectIds.length > 0);

    return {
        searchSubject,
        setSearchSubject,
        searchClass,
        setSearchClass,
        selectedGradeLevelIds,
        selectedSubjectIds,
        cascadingGradeLevelOptions,
        cascadingSubjectOptions,
        matrixDisplaySubjects,
        matrixDisplayClasses,
        mappedPairSet,
        assignmentMap,
        handleGradeLevelFilterChange,
        handleSubjectFilterChange,
        applySubjectsFromMappingPreset,
        hasActiveFilters,
    };
}

export type MatrixClass = Pick<SchoolClass, 'id' | 'name' | 'grade_level_id'>;
export type MatrixSubject = Pick<Subject, 'id' | 'name'>;
export type { GradeSubjectRow };
