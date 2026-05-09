import { Users } from 'lucide-react';
import { teacherInitials } from './teacherColors';

type PresenceMember = {
    id: number;
    name: string;
    role?: string;
    color_seed?: number;
};

type Props = {
    members: PresenceMember[];
    currentUserId?: number;
};

function colorClass(seed: number): string {
    const palette = [
        'bg-sky-500',
        'bg-emerald-500',
        'bg-amber-500',
        'bg-rose-500',
        'bg-violet-500',
        'bg-indigo-500',
        'bg-teal-500',
        'bg-fuchsia-500',
    ];
    return palette[Math.abs(seed) % palette.length] ?? 'bg-slate-500';
}

export default function PresenceBar({ members, currentUserId = 0 }: Props) {
    return (
        <div className="flex items-center gap-2 rounded-md border bg-muted/30 px-2 py-1 text-xs">
            <Users className="h-3.5 w-3.5 text-muted-foreground" />
            <span className="text-muted-foreground">{members.length} online</span>
            <div className="flex items-center gap-1">
                {members.map((member) => (
                    <span
                        key={member.id}
                        title={`${member.name}${member.role ? ` (${member.role})` : ''}`}
                        className={`inline-flex h-6 min-w-[24px] items-center justify-center rounded-full px-1 text-[10px] font-semibold text-white ${colorClass(member.color_seed ?? member.id)}`}
                    >
                        {teacherInitials(member.name)}
                    </span>
                ))}
            </div>
            {currentUserId > 0 && (
                <span className="ml-auto text-[11px] text-muted-foreground">
                    Anda:{' '}
                    {members.some((m) => Number(m.id) === Number(currentUserId)) ? 'online' : 'offline'}
                </span>
            )}
        </div>
    );
}

