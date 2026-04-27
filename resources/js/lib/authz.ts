import type { Auth } from '@/types';

function getRoleNames(auth: Auth | undefined): string[] {
    const user = auth?.user;
    if (!user) return [];

    const directRole = user.role?.name ? [user.role.name] : [];
    const relationRoles = Array.isArray(user.roles)
        ? user.roles
              .map((role) => role?.name)
              .filter((roleName): roleName is string => typeof roleName === 'string' && roleName.length > 0)
        : [];

    return Array.from(new Set([...directRole, ...relationRoles]));
}

export function getEffectivePermissions(auth: Auth | undefined): string[] {
    const fromAuth = Array.isArray(auth?.permissions) ? auth.permissions : [];
    const fromUser = Array.isArray(auth?.user?.permissions) ? auth.user.permissions : [];

    return Array.from(new Set([...fromAuth, ...fromUser].filter((value): value is string => typeof value === 'string' && value.length > 0)));
}

export function can(auth: Auth | undefined, permission: string): boolean {
    if (!permission) return false;
    if (getRoleNames(auth).includes('super_admin')) return true;

    const permissions = getEffectivePermissions(auth);
    return permissions.includes(permission);
}

export function canAny(auth: Auth | undefined, permissionList: string[]): boolean {
    return permissionList.some((permission) => can(auth, permission));
}

