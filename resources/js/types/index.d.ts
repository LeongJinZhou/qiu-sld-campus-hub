export interface AuthUser {
    id: number;
    name: string;
    email: string;
    avatar_url: string | null;
    roles: string[];
}

export interface Flash {
    success: string | null;
    error: string | null;
}

export interface SharedProps {
    auth: { user: AuthUser | null };
    flash: Flash;
    [key: string]: unknown;
}

declare module '@inertiajs/core' {
    interface InertiaConfig {
        sharedPageProps: SharedProps;
    }
}
