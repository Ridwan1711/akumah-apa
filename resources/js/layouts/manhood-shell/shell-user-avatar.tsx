import { useInitials } from '@/hooks/use-initials';
import { getProfilePhotoUrl, resolvePublicProfilePhotoUrl, type WithProfilePhoto } from '@/lib/profile-photo';
import { cn } from '@/lib/utils';
import { useCallback, useEffect, useState } from 'react';

type Props = {
    user: WithProfilePhoto & { name: string };
    /** Sidebar footer */
    variant?: 'sidebar' | 'navbar';
    className?: string;
};

/**
 * Shell avatar: photo with initials fallback on missing URL or image load error.
 */
export function ShellUserAvatar({ user, variant = 'sidebar', className }: Props) {
    const getInitials = useInitials();
    const initials = getInitials(user.name ?? '') || '?';

    const rawUrl = getProfilePhotoUrl(user);
    const [broken, setBroken] = useState(false);

    useEffect(() => {
        setBroken(false);
    }, [rawUrl]);

    const onImgError = useCallback(() => {
        setBroken(true);
    }, []);

    const resolved = rawUrl && !broken ? resolvePublicProfilePhotoUrl(rawUrl) : undefined;

    const base =
        variant === 'navbar'
            ? 'mhs-user-btn-avatar'
            : 'mhs-user-avatar';

    if (!resolved) {
        return (
            <span className={cn(base, className)} aria-hidden="true">
                {initials}
            </span>
        );
    }

    return (
        <span className={cn(base, `${base}--photo`, className)} aria-hidden="true">
            <img src={resolved} alt="" loading="lazy" decoding="async" onError={onImgError} />
        </span>
    );
}
