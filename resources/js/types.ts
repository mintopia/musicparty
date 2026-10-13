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
    external_url: string | null;
    provider_name: string;
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

export interface LegacyArtist {
    name: string;
}

export interface LegacyTrack {
    id?: number;
    provider_track_id?: string;
    name: string;
    artists: LegacyArtist[];
    album: {name: string; image_url: string};
    length: number;
    score: number;
    user: string | null;
    fallback_name: string | null;
    css_classes: string;
}

export interface LegacyPlaybackStatus {
    isPlaying: boolean;
    updatedAt: string;
    progress: number;
    length: number;
}

export interface LegacyPartyState {
    now: LegacyTrack | null;
    next: LegacyTrack | null;
    current: {id: number; provider_track_id?: string; user?: string | null; rating?: number | null} | null;
    status: LegacyPlaybackStatus | null;
    [key: string]: unknown;
}

export interface LegacyTvTrack extends LegacyTrack {
    provider_track_id: string;
}

export interface LegacyTvCurrent {
    id: number;
    provider_track_id: string;
    user: string | null;
    rating: number;
}

export interface LegacyTvPartyState {
    name: string;
    now: LegacyTvTrack | null;
    next: LegacyTvTrack | null;
    current: LegacyTvCurrent;
    status: LegacyPlaybackStatus | null;
    show_qrcode: boolean;
}

export interface LegacyUpcomingSong extends LegacyTrack {
    id: number;
    vote: number;
    created_at: string;
}

export interface PlaybackState {
    isPlaying: boolean;
    trackId: string | null;
    linkedFromId: string | null;
    durationMs: number | null;
    progressMs: number;
    sampledAt: string;
    shuffle: boolean;
    repeat: string;
    deviceId: string | null;
    deviceName: string | null;
    source: string;
}
