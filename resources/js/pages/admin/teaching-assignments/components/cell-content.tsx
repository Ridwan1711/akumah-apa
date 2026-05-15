import { Eraser, Loader2, RefreshCw, UserPlus } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import type { TeacherAssignment } from '@/types';

type CellContentProps = {
    assignment?: TeacherAssignment;
    isBusy: boolean;
    isMapped: boolean;
    onAssign: () => void;
    onRemove: (a: TeacherAssignment) => void;
    hasTeacherSelected: boolean;
};

export function CellContent({
    assignment,
    isBusy,
    isMapped,
    onAssign,
    onRemove,
    hasTeacherSelected,
}: CellContentProps) {
    if (isBusy) {
        return (
            <div className="flex h-[72px] w-full items-center justify-center gap-2 rounded-lg border border-dashed bg-muted/30">
                <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />
                <span className="text-xs text-muted-foreground">Memproses...</span>
            </div>
        );
    }

    if (!isMapped) {
        return (
            <TooltipProvider>
                <Tooltip>
                    <TooltipTrigger asChild>
                        <div className="flex h-[72px] w-full flex-col items-center justify-center gap-1 rounded-lg border border-dashed border-amber-300 bg-amber-50/70 px-2 text-center text-amber-800">
                            <span className="text-[10px] font-medium">Tidak tersedia</span>
                            <span className="text-[10px] leading-tight">Pelajaran ini gak dipelajari di sini</span>
                        </div>
                    </TooltipTrigger>
                    <TooltipContent>
                        Pelajaran ini gak dipelajari di sini
                    </TooltipContent>
                </Tooltip>
            </TooltipProvider>
        );
    }

    if (assignment) {
        return (
            <div className="group relative flex flex-col gap-1 rounded-lg border border-emerald-200 bg-emerald-50 p-2.5 transition-all hover:shadow-sm dark:border-emerald-900 dark:bg-emerald-950/30">
                <div className="flex items-start gap-1.5">
                    <div className="mt-0.5 h-2 w-2 shrink-0 rounded-full bg-emerald-500" />
                    <span className="text-xs font-semibold leading-tight text-foreground line-clamp-2">
                        {assignment.teacher?.name ?? '-'}
                    </span>
                </div>
                <div className="flex items-center gap-1">
                    <Badge
                        variant="outline"
                        className="border-emerald-300 text-[10px] text-emerald-700 px-1.5 py-0 dark:border-emerald-800 dark:text-emerald-400"
                    >
                        {assignment.target_jam} jam/mgg
                    </Badge>
                </div>
                <div className="absolute inset-0 flex items-center justify-center gap-1 rounded-lg opacity-0 transition-opacity group-hover:opacity-100 bg-card/80 dark:bg-black/60 backdrop-blur-[2px]">
                    <TooltipProvider>
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button
                                    size="sm"
                                    variant="default"
                                    className="h-7 gap-1 text-xs"
                                    onClick={onAssign}
                                    onMouseDown={(e) => e.stopPropagation()}
                                    disabled={!hasTeacherSelected}
                                >
                                    <RefreshCw className="h-3 w-3" />
                                    Ganti
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                {hasTeacherSelected ? 'Ganti guru' : 'Pilih guru dulu'}
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                    <Button
                        size="sm"
                        variant="destructive"
                        className="h-7 gap-1 text-xs"
                        onClick={() => onRemove(assignment)}
                        onMouseDown={(e) => e.stopPropagation()}
                    >
                        <Eraser className="h-3 w-3" />
                        Hapus
                    </Button>
                </div>
            </div>
        );
    }

    return (
        <TooltipProvider>
            <Tooltip>
                <TooltipTrigger asChild>
                    <button
                        onClick={onAssign}
                        className={`flex h-[72px] w-full flex-col items-center justify-center gap-1 rounded-lg border border-dashed
                            transition-all text-muted-foreground
                            ${hasTeacherSelected
                                ? 'hover:border-primary hover:bg-primary/5 hover:text-primary cursor-pointer'
                                : 'opacity-50 cursor-not-allowed'
                            }`}
                        disabled={!hasTeacherSelected}
                    >
                        <UserPlus className="h-4 w-4" />
                        <span className="text-[10px]">Kosong</span>
                    </button>
                </TooltipTrigger>
                <TooltipContent>
                    {hasTeacherSelected ? 'Klik untuk assign guru' : 'Pilih guru terlebih dahulu'}
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
}
