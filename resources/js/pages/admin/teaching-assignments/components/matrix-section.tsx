import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import type { AssignmentMatrixTableProps } from './assignment-matrix-table';
import { AssignmentMatrixTable } from './assignment-matrix-table';

type MatrixSectionProps = AssignmentMatrixTableProps & {
    isBulkAssigning: boolean;
};

export function MatrixSection({
    isBulkAssigning,
    ...tableProps
}: MatrixSectionProps) {
    const { filteredSubjects, filteredClasses } = tableProps;

    return (
        <Card className="overflow-hidden shadow-sm flex-1">
            <CardHeader className="flex flex-row items-center justify-between border-b py-3 px-5">
                <div>
                    <CardTitle className="text-sm font-semibold">Matriks Penugasan</CardTitle>
                    <CardDescription className="text-xs mt-0.5">
                        {filteredSubjects.length} mata pelajaran · {filteredClasses.length} kelas
                    </CardDescription>
                </div>
                <div className="flex items-center gap-2">
                    {isBulkAssigning && (
                        <Badge variant="secondary" className="text-[10px]">
                            Bulk assigning...
                        </Badge>
                    )}
                    <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
                        <span className="flex h-3 w-3 rounded-full bg-emerald-500" />
                        Terisi
                    </div>
                    <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
                        <span className="flex h-3 w-3 rounded-full border-2 border-dashed border-muted-foreground/40" />
                        Kosong
                    </div>
                </div>
            </CardHeader>
            <CardContent className="p-0">
                <div className="overflow-x-auto">
                    <AssignmentMatrixTable {...tableProps} />
                </div>
            </CardContent>
        </Card>
    );
}
