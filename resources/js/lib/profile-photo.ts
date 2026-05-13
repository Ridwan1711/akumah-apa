/** Props from Laravel User (snake_case) or any camelCase alias. */
export type WithProfilePhoto = {
    name?: string | null;
    profile_photo_url?: string | null;
    profilePhotoUrl?: string | null;
};

export function getProfilePhotoUrl(user: WithProfilePhoto | null | undefined): string | null {
    if (!user) return null;
    const raw = user.profile_photo_url ?? user.profilePhotoUrl;
    if (typeof raw !== 'string') return null;
    const t = raw.trim();
    return t.length > 0 ? t : null;
}

/** Ensure <img src> works when the API returns a host-relative path. */
export function resolvePublicProfilePhotoUrl(url: string | null | undefined): string | undefined {
    const u = url?.trim();
    if (!u) return undefined;
    if (u.startsWith('http://') || u.startsWith('https://')) return u;
    if (u.startsWith('//')) return `https:${u}`;
    if (typeof window !== 'undefined' && u.startsWith('/')) {
        return `${window.location.origin}${u}`;
    }
    return u;
}
