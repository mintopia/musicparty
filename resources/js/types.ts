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
    theme: {darkMode: boolean; tokens: Record<string, string>; stylesheet: string | null};
    reverb: RealtimeConfig;
    instanceName: string | null;
    errors: Record<string, string>;
    [key: string]: unknown;
}

export interface RealtimeConfig {
    appKey: string | null;
    host: string;
    port: number;
    forceTLS: boolean;
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
    eligibility: {allowed: boolean; outcome: 'allow' | 'flag' | 'reject'; reason: string | null};
    awaiting_approval?: boolean;
    flagged_request_id?: number | null;
    decoration?: TrackDecoration | null;
}

export interface FlaggedRequest {
    id: number;
    reason: string | null;
    flagged_at: string;
    requester: {id: number; name: string};
    track: TrackAnnotation;
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
    decoration?: TrackDecoration | null;
}

export interface LegacyPlaybackStatus {
    isPlaying: boolean;
    sampledAt: string;
    progressMs: number;
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
    current: LegacyTvCurrent | null;
    status: LegacyPlaybackStatus | null;
    show_qrcode: boolean;
}

export interface LegacyUpcomingSong extends LegacyTrack {
    id: number;
    vote: number;
    created_at: string;
    scheduled_for?: string | null;
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

export type ModSettingType =
    'string' | 'boolean' | 'integer' | 'float' | 'datetime' | 'track' | 'time_of_day' | 'secret' | 'choice' | 'user';

export interface ModSetting {
    code: string;
    name: string;
    description: string;
    type: ModSettingType;
    value: unknown;
    has_value: boolean;
    default: unknown;
    required: boolean;
    options?: Record<string, string>;
    value_label?: string | null;
}

export interface PartyMod {
    code: string;
    name: string;
    description: string;
    enabled: boolean;
    settings: ModSetting[];
}

export interface AdminMod {
    code: string;
    name: string;
    description: string;
    settings: ModSetting[];
}

export interface When {
    iso: string;
    human: string;
}

export type DashboardPlayerSource = 'polling' | 'webhook' | 'webplayer' | 'encore';

export interface DashboardDevice {
    id: string;
    name: string;
    type: string;
    isActive: boolean;
    isRestricted: boolean;
    label: string;
    icon: string;
}

export type DashboardStartState =
    'hidden' | 'idle' | 'resume' | 'outside' | 'no_device' | 'premium_required' | 'relink_required';

export interface DashboardStartTarget {
    kind: 'resume' | 'next' | 'select';
    track: LegacyTrack;
    positionMs: number;
    requestedBy: string | null;
}

export interface DashboardControl {
    playingFrom: {kind: 'party' | 'outside' | 'nothing' | 'unknown'; label: string | null};
    nowPlaying: {isRequest: boolean; requestedBy: string | null};
    health: {
        player: {source: DashboardPlayerSource | null; label: string; fresh: boolean};
        device: {id: string | null; name: string | null};
        sampledAt: string | null;
        link: 'connected' | 'relink_required';
        polling: 'off' | 'on' | 'backup';
    };
    start: {
        state: DashboardStartState;
        target: DashboardStartTarget | null;
        devices: DashboardDevice[];
        selectedDeviceId: string | null;
    };
}

export interface DashboardRequest extends LegacyUpcomingSong {
    sent: boolean;
}

export type DashboardIssueCode = 'relink_required' | 'party_inactive' | 'player_stale' | 'premium_required';

export interface DashboardIssue {
    code: DashboardIssueCode;
    fact: string;
    detail: string;
    action: {label: string; kind: 'activate'} | null;
}

export interface DashboardProps {
    party: {code: string; name: string; active: boolean; weighted: boolean; downvotes: boolean; canManage: boolean};
    host: {name: string};
    can: {control: boolean; moderate: boolean; player: boolean};
    timezone: string;
    playerState: LegacyPartyState;
    control: DashboardControl;
    upNext: DashboardRequest[];
    upcoming: {items: DashboardRequest[]; total: number};
    attention: {issues: DashboardIssue[]; flagged: FlaggedRequest[]; flaggedTotal: number};
    links: {
        queue: string;
        songs: string;
        flagged: string;
        settings: string;
        members: string;
        moderation: string;
        mods: string;
        tv: string;
        webPlayer: string | null;
        encoreRelay: string;
        join: string;
        fallbackSettings: string;
    };
    hasFallbackPlaylist: boolean;
}

export interface SearchError {
    error: {code: string; message: string; retryable: boolean};
}
