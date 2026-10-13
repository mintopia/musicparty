export interface AuthUser {
    id: number;
    nickname: string;
    avatar: string;
    roles: string[];
    impersonating: boolean;
}

export interface PartyMembership {
    code: string;
    name: string;
    canManage: boolean;
}

export interface SharedProps {
    auth: {user: AuthUser | null; parties: PartyMembership[]; can: {admin: boolean; createParty: boolean}};
    flash: {success: string | null; error: string | null; info: string | null; warning: string | null};
    site: {
        defaultParty: string | null;
        logoLight: string | null;
        logoDark: string | null;
        terms: string | null;
        privacyPolicy: string | null;
        coverImage: string | null;
        favicon: string | null;
    };
    theme: {darkMode: boolean};
    reverb: {appKey: string | null};
    instanceName: string | null;
    errors: Record<string, string>;
    [key: string]: unknown;
}

export interface TrackAnnotation {
    provider_id: string;
    id: string;
    name: string;
    duration_ms: number;
    explicit: boolean;
    album: {id: string; name: string; image_url: string | null};
    artists: {id: string; name: string}[];
    queue: {
        in_queue: boolean;
        upcoming_id: number | null;
        score: number;
        vote: number | null;
        requested_by: string | null;
    };
    eligibility: {allowed: boolean; reason: string | null};
}

export interface TrackDecoration {
    displayName?: string;
    label?: string;
    class?: string;
    accent?: string;
}

export type LinkTarget = string | {url: string};

export interface PaginatorLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface Paginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    links: PaginatorLink[];
}
