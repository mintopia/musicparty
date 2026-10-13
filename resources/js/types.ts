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
