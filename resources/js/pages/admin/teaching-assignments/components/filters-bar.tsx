import { School, Search, X } from 'lucide-react';
import type { MultiValue } from 'react-select';
import { AppMultiSelect } from '@/components/manhood';
import type { SelectOption } from '@/components/manhood';
import { Input } from '@/components/ui/input';

type FiltersBarProps = {
    cascadingGradeLevelOptions: SelectOption[];
    cascadingSubjectOptions: SelectOption[];
    selectedGradeLevelIds: string[];
    selectedSubjectIds: string[];
    onGradeLevelFilterChange: (items: MultiValue<SelectOption>) => void;
    onSubjectFilterChange: (items: MultiValue<SelectOption>) => void;
    searchSubject: string;
    onSearchSubjectChange: (value: string) => void;
    searchClass: string;
    onSearchClassChange: (value: string) => void;
    matrixDisplaySubjectsCount: number;
    matrixDisplayClassesCount: number;
    hasActiveFilters: boolean;
};

export function FiltersBar({
    cascadingGradeLevelOptions,
    cascadingSubjectOptions,
    selectedGradeLevelIds,
    selectedSubjectIds,
    onGradeLevelFilterChange,
    onSubjectFilterChange,
    searchSubject,
    onSearchSubjectChange,
    searchClass,
    onSearchClassChange,
    matrixDisplaySubjectsCount,
    matrixDisplayClassesCount,
    hasActiveFilters,
}: FiltersBarProps) {
    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-wrap items-end gap-3">
                <div className="flex min-w-[220px] flex-1 max-w-md flex-col gap-1.5">
                    <label className="text-xs font-medium text-muted-foreground">Tingkat kelas</label>
                    <AppMultiSelect
                        options={cascadingGradeLevelOptions}
                        value={cascadingGradeLevelOptions.filter((opt) =>
                            selectedGradeLevelIds.includes(String(opt.value)),
                        )}
                        onChange={onGradeLevelFilterChange}
                        placeholder="Semua tingkat…"
                    />
                    {selectedSubjectIds.length > 0 && (
                        <p className="text-[10px] text-muted-foreground">
                            Hanya tingkat yang memiliki mapel terpilih
                        </p>
                    )}
                </div>
                <div className="flex min-w-[220px] flex-1 max-w-md flex-col gap-1.5">
                    <label className="text-xs font-medium text-muted-foreground">Mata pelajaran</label>
                    <AppMultiSelect
                        options={cascadingSubjectOptions}
                        value={cascadingSubjectOptions.filter((opt) =>
                            selectedSubjectIds.includes(String(opt.value)),
                        )}
                        onChange={onSubjectFilterChange}
                        placeholder="Semua mapel…"
                    />
                </div>
                <div className="relative min-w-[200px] flex-1 max-w-xs">
                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        placeholder="Cari mata pelajaran..."
                        value={searchSubject}
                        onChange={(e) => onSearchSubjectChange(e.target.value)}
                        className="pl-9"
                    />
                    {searchSubject && (
                        <button
                            type="button"
                            onClick={() => onSearchSubjectChange('')}
                            className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                        >
                            <X className="h-3.5 w-3.5" />
                        </button>
                    )}
                </div>
                <div className="relative min-w-[200px] flex-1 max-w-xs">
                    <School className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        placeholder="Cari kelas..."
                        value={searchClass}
                        onChange={(e) => onSearchClassChange(e.target.value)}
                        className="pl-9"
                    />
                    {searchClass && (
                        <button
                            type="button"
                            onClick={() => onSearchClassChange('')}
                            className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                        >
                            <X className="h-3.5 w-3.5" />
                        </button>
                    )}
                </div>

                {hasActiveFilters && (
                    <p className="text-xs text-muted-foreground self-center">
                        Menampilkan{' '}
                        <span className="font-medium">{matrixDisplaySubjectsCount}</span> mapel ·{' '}
                        <span className="font-medium">{matrixDisplayClassesCount}</span> kelas
                    </p>
                )}
            </div>
        </div>
    );
}
