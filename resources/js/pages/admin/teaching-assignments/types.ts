import type {
    AcademicYear,
    GradeLevel,
    SchoolClass,
    Semester,
    Subject,
    TeacherAssignment,
    User,
} from '@/types';

export type TeachingAssignmentPageProps = {
    assignments: TeacherAssignment[];
    teachers: Pick<User, 'id' | 'name'>[];
    activeAcademicYear: Pick<AcademicYear, 'id' | 'name'> | null;
    classes: Pick<SchoolClass, 'id' | 'name' | 'grade_level_id'>[];
    gradeLevels: Pick<GradeLevel, 'id' | 'name' | 'order'>[];
    subjects: Pick<Subject, 'id' | 'name'>[];
    gradeSubjects: Array<{ grade_level_id: number; subject_id: number }>;
    semesters: (Pick<Semester, 'id' | 'name'> & {
        academic_year_name?: string | null;
        is_active?: boolean;
    })[];
    selectedPeriodId: number;
    selectedSemesterId: number;
};

export type ToastState = { message: string; type: 'success' | 'error' } | null;

export type GradeSubjectRow = { grade_level_id: number; subject_id: number };
