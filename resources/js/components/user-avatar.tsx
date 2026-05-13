import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { getProfilePhotoUrl, resolvePublicProfilePhotoUrl } from '@/lib/profile-photo';
import { cn } from '@/lib/utils';

export type UserAvatarUser = {
    name: string;
    profile_photo_url?: string | null;
    profilePhotoUrl?: string | null;
};

type Props = {
    user: UserAvatarUser;
    className?: string;
    /** Applied to Avatar root (e.g. size, rounded-full). */
    rootClassName?: string;
    /** Applied to AvatarFallback. */
    fallbackClassName?: string;
};

/**
 * Profile image uses server `profile_photo_url` (custom → official); initials when missing or on load error.
 */
export function UserAvatar({ user, className, rootClassName, fallbackClassName }: Props) {
    const getInitials = useInitials();
    const raw = getProfilePhotoUrl(user);
    const src = raw ? resolvePublicProfilePhotoUrl(raw) : undefined;

    return (
        <Avatar className={cn('size-8 overflow-hidden rounded-lg', rootClassName, className)}>
            <AvatarImage src={src} alt={user.name} />
            <AvatarFallback
                className={cn(
                    'rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white',
                    fallbackClassName,
                )}
            >
                {getInitials(user.name || '') || '?'}
            </AvatarFallback>
        </Avatar>
    );
}
