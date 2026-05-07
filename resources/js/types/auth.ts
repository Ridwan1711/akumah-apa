export type Role = {
    id: number;
    name: string;
    guard_name: string;
};

export type User = {
    id: number;
    name: string;
    username: string | null;
    email: string;
    whatsapp_phone?: string | null;
    google_connected?: boolean;
    avatar?: string;
    official_photo_path?: string | null;
    custom_photo_path?: string | null;
    profile_photo_url?: string | null;
    has_official_photo?: boolean;
    email_verified_at: string | null;
    role_id: number | null;
    role: Role | null;
    roles?: Role[];
    permissions?: string[];
    permissionScopes?: PermissionScope[];
    is_active: boolean;
    must_change_password: boolean;
    must_complete_profile?: boolean;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    permissions?: string[];
    permissionScopes?: PermissionScope[];
};

export type PermissionScope = {
    permission_name: string;
    scope_key: string;
    scope_value: string;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
