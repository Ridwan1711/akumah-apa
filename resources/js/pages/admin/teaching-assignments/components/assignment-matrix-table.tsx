import { BookOpen, School, Search } from 'lucide-react';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { SchoolClass, Subject, TeacherAssignment } from '@/types';
import { CellContent } from './cell-content';

export type AssignmentMatrixTableProps = {
    filteredClasses: Pick<SchoolClass, 'id' | 'name' | 'grade_level_id'>[];
    filteredSubjects: Pick<Subject, 'id' | 'name'>[];
    assignmentMap: Map<string, TeacherAssignment>;
    mappedPairSet: Set<string>;
    busyKey: string | null;
    onAssign: (classId: number, subjectId: number) => void;
    onRemove: (assignment: TeacherAssignment) => void;
    hasTeacherSelected: boolean;
    onDragStartCell: (classId: number, subjectId: number) => void;
    onDragEnterCell: (classId: number, subjectId: number) => void;
    onDragEnd: () => void;
};

export function AssignmentMatrixTable({
    filteredClasses,
    filteredSubjects,
    assignmentMap,
    mappedPairSet,
    busyKey,
    onAssign,
    onRemove,
    hasTeacherSelected,
    onDragStartCell,
    onDragEnterCell,
    onDragEnd,
}: AssignmentMatrixTableProps) {
    if (filteredSubjects.length === 0 || filteredClasses.length === 0) {
        return (
            <div className="flex flex-col items-center justify-center gap-3 py-20 text-muted-foreground">
                <Search className="h-10 w-10 opacity-30" />
                <p className="text-sm text-center">
                    Tidak ada mapel/kelas yang cocok dengan filter atau pencarian.
                </p>
            </div>
        );
    }

    return (
        <Table>
            <TableHeader>
                <TableRow className="bg-muted/50">
                    <TableHead className="sticky left-0 z-20 min-w-[180px] bg-muted/80 backdrop-blur-sm font-semibold text-xs uppercase tracking-wider">
                        Mata Pelajaran
                    </TableHead>
                    {filteredClasses.map((cls) => (
                        <TableHead key={cls.id} className="min-w-[160px] text-center text-xs font-semibold uppercase tracking-wider">
                            <div className="flex items-center justify-center gap-1.5">
                                <School className="h-3.5 w-3.5 text-muted-foreground" />
                                {cls.name}
                            </div>
                        </TableHead>
                    ))}
                </TableRow>
            </TableHeader>
            <TableBody>
                {filteredSubjects.map((subject) => (
                    <TableRow key={subject.id} className="hover:bg-muted/20">
                        <TableCell className="sticky left-0 z-10 bg-background border-r">
                            <div className="flex items-center gap-2 py-0.5">
                                <div className="flex h-7 w-7 items-center justify-center rounded-md bg-primary/10 shrink-0">
                                    <BookOpen className="h-3.5 w-3.5 text-primary" />
                                </div>
                                <span className="text-sm font-medium leading-tight">{subject.name}</span>
                            </div>
                        </TableCell>
                        {filteredClasses.map((cls) => {
                            const key = `${subject.id}:${cls.id}`;
                            const assignment = assignmentMap.get(key);
                            const isBusy = busyKey === key;
                            const isMapped = mappedPairSet.has(`${cls.grade_level_id}:${subject.id}`);

                            return (
                                <TableCell
                                    key={key}
                                    className={`p-2 align-top ${hasTeacherSelected && isMapped ? 'cursor-crosshair select-none' : ''}`}
                                    onMouseDown={(e) => {
                                        if (!hasTeacherSelected || !isMapped) return;
                                        e.preventDefault();
                                        onDragStartCell(cls.id, subject.id);
                                    }}
                                    onMouseEnter={() => {
                                        if (!hasTeacherSelected || !isMapped) return;
                                        onDragEnterCell(cls.id, subject.id);
                                    }}
                                    onMouseUp={() => {
                                        if (!hasTeacherSelected || !isMapped) return;
                                        onDragEnd();
                                    }}
                                >
                                    <CellContent
                                        assignment={assignment}
                                        isBusy={isBusy}
                                        isMapped={isMapped}
                                        onAssign={() => onAssign(cls.id, subject.id)}
                                        onRemove={onRemove}
                                        hasTeacherSelected={hasTeacherSelected}
                                    />
                                </TableCell>
                            );
                        })}
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
