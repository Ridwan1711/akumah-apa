import { Head } from '@inertiajs/react';
import FlashMessage from '@/components/flash-message';
import AppLayout from '@/layouts/app-layout';
import { ControlPanel } from './components/control-panel';
import { FiltersBar } from './components/filters-bar';
import { MatrixSection } from './components/matrix-section';
import { PageHeader } from './components/page-header';
import { Toast } from './components/toast';
import { breadcrumbs } from './constants';
import { useAssignmentActions } from './hooks/use-assignment-actions';
import { useMatrixFilters } from './hooks/use-matrix-filters';
import type { TeachingAssignmentPageProps } from './types';

export default function TeachingAssignmentIndex({
    assignments,
    teachers,
    classes,
    gradeLevels,
    subjects,
    gradeSubjects,
    semesters,
    activeAcademicYear,
    selectedSemesterId,
}: TeachingAssignmentPageProps) {
    const academicYearLabel = activeAcademicYear?.name ?? 'Belum ditentukan';

    const matrix = useMatrixFilters({
        assignments,
        classes,
        gradeLevels,
        subjects,
        gradeSubjects,
    });

    const actions = useAssignmentActions({
        teachers,
        semesters,
        selectedSemesterId,
        assignmentMap: matrix.assignmentMap,
    });

    const totalAssignments = assignments.length;
    const uniqueTeachers = new Set(assignments.map((a) => a.teacher_id)).size;
    const totalCells = classes.length * subjects.length;
    const fillRate = totalCells > 0 ? Math.round((totalAssignments / totalCells) * 100) : 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Penugasan Guru" />
            <Toast toast={actions.toast} onClose={() => actions.setToast(null)} />

            <div className="flex h-full flex-1 flex-col gap-5 p-6">
                <PageHeader
                    academicYearLabel={academicYearLabel}
                    totalAssignments={totalAssignments}
                    uniqueTeachers={uniqueTeachers}
                    fillRate={fillRate}
                />

                <FlashMessage />

                <ControlPanel
                    semesters={semesters}
                    semesterId={actions.semesterId}
                    defaultPeriodId={actions.defaultPeriodId}
                    isRefreshing={actions.isRefreshing}
                    onSemesterChange={actions.handleSemesterChange}
                    teacherSelectOptions={actions.teacherSelectOptions}
                    selectedTeacherOption={actions.selectedTeacherOption}
                    onTeacherChange={actions.setSelectedTeacherId}
                    selectedTeacher={actions.selectedTeacher}
                />

                <FiltersBar
                    cascadingGradeLevelOptions={matrix.cascadingGradeLevelOptions}
                    cascadingSubjectOptions={matrix.cascadingSubjectOptions}
                    selectedGradeLevelIds={matrix.selectedGradeLevelIds}
                    selectedSubjectIds={matrix.selectedSubjectIds}
                    onGradeLevelFilterChange={matrix.handleGradeLevelFilterChange}
                    onSubjectFilterChange={matrix.handleSubjectFilterChange}
                    searchSubject={matrix.searchSubject}
                    onSearchSubjectChange={matrix.setSearchSubject}
                    searchClass={matrix.searchClass}
                    onSearchClassChange={matrix.setSearchClass}
                    matrixDisplaySubjectsCount={matrix.matrixDisplaySubjects.length}
                    matrixDisplayClassesCount={matrix.matrixDisplayClasses.length}
                    hasActiveFilters={matrix.hasActiveFilters}
                />

                <MatrixSection
                    isBulkAssigning={actions.isBulkAssigning}
                    filteredClasses={matrix.matrixDisplayClasses}
                    filteredSubjects={matrix.matrixDisplaySubjects}
                    assignmentMap={matrix.assignmentMap}
                    mappedPairSet={matrix.mappedPairSet}
                    busyKey={actions.busyKey}
                    onAssign={actions.assignTeacher}
                    onRemove={actions.removeAssignment}
                    hasTeacherSelected={!!actions.selectedTeacherId}
                    onDragStartCell={actions.handleDragStartCell}
                    onDragEnterCell={actions.handleDragEnterCell}
                    onDragEnd={actions.handleDragEnd}
                />
            </div>
        </AppLayout>
    );
}
