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
    nav: { unread: number; actions: number; isStaff: boolean; isAdmin: boolean; canPublish: boolean } | null;
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

export interface ProposalData {
    name: string;
    summary: string;
    starts_at: string;
    ends_at: string;
    venue: string;
    mode: 'physical' | 'virtual';
    is_outdoor: boolean;
    is_faculty_event: boolean;
    expected_participants: number;
    nature: string[];
    nature_other: string;
    cca_participant: number;
    cca_committee: number;
    sdgs: number[];
    collaboration: string;
    source_of_fund: string[];
    introduction: string;
    objectives: string[];
    eligibility: string;
    activities: { name: string; description: string }[];
    itinerary: { start: string; end: string; agenda: string }[];
    ict_requirements: string;
    transport_required: boolean;
    transport_arrangement: string;
    departure_time: string;
    departure_pickup: string;
    return_time: string;
    return_pickup: string;
    budget: { revenue: { description: string; price_sen: number; quantity: number }[]; expenses: { description: string; price_sen: number; quantity: number }[] };
    conclusion: string;
}

export interface ApprovalStep {
    id: number;
    step_no: number;
    role: string;
    decision: 'pending' | 'approved' | 'returned' | 'rejected';
    is_current: boolean;
    approver: string | null;
    position: string | null;
    comment: string | null;
    decided_at: string | null;
    due_at: string | null;
    verification_code: string | null;
    waiting_on: string[];
}

export interface PresentedDocument {
    id: number;
    type: string;
    type_label: string;
    status: string;
    version: number | null;
    club: { slug: string; name: string };
    event: { slug: string; name: string } | null;
    agm_id: number | null;
    submitted_at: string | null;
    data: ProposalData;
    locked: { to?: string; from?: string; submitted_on?: string | null };
    totals: { revenue_sen: number; expenses_sen: number; profit_sen: number; covers_expenses: boolean } | null;
    committee: { name: string; position: string; responsibility: string | null }[];
    options: { natures?: Record<string, string>; sdgs?: Record<number, string>; fundSources?: Record<string, string> };
    progress: ApprovalProgress;
    steps: ApprovalStep[];
}

export interface PostCardData {
    id: number;
    type: 'event' | 'recap' | 'general';
    caption: string | null;
    pinned: boolean;
    published_at: string;
    club: { slug: string; name: string; logo_url: string | null } | null;
    media: string[];
    event: { slug: string; name: string; starts_at: string; ends_at: string; venue: string | null; registration_open: boolean } | null;
    likes_count: number;
    comments_count: number;
    liked: boolean;
    saved: boolean;
    comments: CommentData[];
    url: string;
}

export interface CommentData {
    id: number;
    body: string;
    user: string;
    photo_url: string | null;
    at: string | null;
}

export interface StoryGroup {
    slug: string;
    name: string;
    logo_url: string | null;
    stories: { id: number; url: string; at: string | null }[];
}

export interface ProfileData {
    name: string;
    photo_url: string | null;
    cover_url: string | null;
    programme: string | null;
    programme_short: string | null;
    year_group: string | null;
    status: 'active' | 'alumni' | 'unknown' | null;
    intake: string | null;
    title: string | null;
    department: string | null;
    stats: { clubs: number | null; positions: number | null; events: number | null; cca_hours: number | null };
    clubs: { name: string; url: string; logo_url: string | null; category: string | null; current_position: string | null; membership: string | null }[] | null;
    positions: { session: string | null; organisation: string | null; url: string | null; position: string; kind: 'club' | 'event' }[] | null;
    events: { name: string; url: string; club: string; date: string; role: string; poster_url: string | null }[] | null;
    cca: { total: number; records: { event: string; date: string; role: string; hours: number }[] } | null;
}

export interface ApprovalProgress {
    done: number;
    total: number;
    status: 'draft' | 'in_review' | 'returned' | 'rejected' | 'approved';
    current_role: string | null;
    waiting_on: string[];
    due_at: string | null;
    overdue: boolean;
}
