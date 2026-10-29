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

export interface MemberCard {
    assignment_id: number;
    position: string | null;
    is_compulsory: boolean;
    name: string;
    photo_url: string | null;
    programme_short: string | null;
    year_group: string | null;
    title: string | null;
    profile_url: string;
}

export interface SessionOption {
    id: number;
    name: string;
}

export interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
}

export interface Option {
    value: string | number;
    label: string;
}
