export interface AuthUser {
    id: number;
    nickname: string;
    avatar: string;
    roles: string[];
    impersonating: boolean;
}

export interface ModerationTabsProps {
    flaggedCount: number;
    aiReviewEnabled: boolean;
}

export interface PartyMembership {
    code: string;
    name: string;
    canManage: boolean;
    canModerate?: boolean;
    aiReviewEnabled?: boolean;
    flaggedCount?: number;
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
        requested_by_you?: boolean;
        requested_ago?: string | null;
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
    ai_review_url: string | null;
    rule_url?: string | null;
    ai_review?: {criterion: string | null; confidence: number | null; reason: string | null} | null;
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
    user_id?: number | null;
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

export interface MyRating {
    playedSongId: number;
    value: -1 | 0 | 1;
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
    | 'string'
    | 'text'
    | 'boolean'
    | 'integer'
    | 'float'
    | 'datetime'
    | 'track'
    | 'time_of_day'
    | 'secret'
    | 'choice'
    | 'user';

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
    review_stats?: {last_review_at: string | null; reviews_today: number} | null;
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
    nowPlaying: {isRequest: boolean; isFallback: boolean; requestedBy: string | null};
    health: {
        player: {source: DashboardPlayerSource | null; label: string; fresh: boolean; connected: boolean | null};
        device: {id: string | null; name: string | null};
        receivedAt: string | null;
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

export type DashboardIssueCode =
    'relink_required' | 'party_inactive' | 'player_stale' | 'premium_required' | 'no_device' | 'outside';

export type DashboardIssueAction = 'activate' | 'open_web_player' | 'start' | 'relink' | 'refresh_devices';

export interface DashboardIssue {
    code: DashboardIssueCode;
    fact: string;
    detail: string;
    action: {label: string; kind: DashboardIssueAction} | null;
}

export interface DashboardProps {
    party: {
        code: string;
        name: string;
        active: boolean;
        weighted: boolean;
        downvotes: boolean;
        canManage: boolean;
        canModerate?: boolean;
    };
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
        relink: string;
        join: string;
        fallbackSettings: string;
    };
    hasFallbackPlaylist: boolean;
    myRating?: MyRating | null;
}

export interface SearchError {
    error: {code: string; message: string; retryable: boolean};
}

export interface AlbumResult {
    id: string;
    name: string;
    image_url: string | null;
    album_type: string;
    artists: {id: string; name: string}[];
    release_year: string | null;
}

export interface ArtistResult {
    id: string;
    name: string;
    image_url: string | null;
}

export type SearchType = 'track' | 'album' | 'artist';

export type SearchTopResult = {kind: 'track'; track: TrackAnnotation} | {kind: 'artist'; artist: ArtistResult};

export interface SearchOverviewResults {
    view: 'overview';
    top: SearchTopResult | null;
    tracks: TrackAnnotation[];
    albums: AlbumResult[];
    artists: ArtistResult[];
    empty: boolean;
    weak: boolean;
}

export interface SearchSuggestions {
    nowPlaying: {name: string; artist: string; image_url: string | null; query: string} | null;
    artists: (ArtistResult & {requests: number})[];
}

export interface SearchPage<T> extends Paginator<T> {
    total: number;
    from: number | null;
    to: number | null;
}

export type SearchTypeResults =
    | {view: 'type'; type: 'track'; total: number; capped: boolean; items: SearchPage<TrackAnnotation>}
    | {view: 'type'; type: 'album'; total: number; capped: boolean; items: SearchPage<AlbumResult>}
    | {view: 'type'; type: 'artist'; total: number; capped: boolean; items: SearchPage<ArtistResult>};

export type SearchResults = SearchOverviewResults | SearchTypeResults;

export type AiReviewOutcome = 'allow' | 'flag' | 'reject' | 'error';

export interface AiReviewRow {
    id: number;
    createdAt: When | null;
    user: string | null;
    track: {title: string; artists: string};
    outcome: AiReviewOutcome;
    outcomeLabel: string;
    reason: string | null;
    criterion: string | null;
    criterionLabel: string | null;
    criterionConfidence: number | null;
    driver: string;
    model: string | null;
    latencyMs: number;
    promptTokens: number | null;
    completionTokens: number | null;
    totalTokens: number | null;
    requested: boolean;
}

export interface AiReviewDetail extends AiReviewRow {
    endpointHost: string | null;
    score: number | null;
    confidence: number | null;
    request: Record<string, unknown>;
    response: Record<string, unknown> | null;
    errorClass: string | null;
    errorMessage: string | null;
    trackId: string;
    trackProvider: string;
    threshold: number;
    rejectBelow: number;
    flagBelow: number;
    fate: AiReviewFate;
}

export interface AiReviewFate {
    state: 'held' | 'queued' | 'played' | 'rejected' | 'gone';
    requestUrl: string | null;
    approveUrl: string | null;
    rejectUrl: string | null;
    flaggedAt: {iso: string; human: string} | null;
    playedAt: {iso: string; human: string} | null;
}

export interface AdminDashboardAction {
    label: string;
    url: string;
}

export interface AdminDashboardParty {
    code: string;
    name: string;
    url: string;
    owner: string;
    state: 'playing' | 'paused' | 'stale' | 'idle' | 'error';
    stateLabel: string | null;
    stateDetail: string | null;
    track: {name: string; artists: string; image: string | null; lastKnown: boolean} | null;
    guestsOnline: number | null;
    requestsWaiting: number;
    flagged: number;
}

export interface AdminDashboardProps {
    generatedAt: string;
    liveParties: AdminDashboardParty[];
    quietFor: string | null;
    attention: {key: string; severity: 'error' | 'warn'; title: string; detail: string; action: AdminDashboardAction}[];
    health: {key: string; label: string; status: 'ok' | 'warn' | 'error'; detail: string; link: string | null}[];
    activity: {
        stats: {signUpsThisWeek: number; partiesThisWeek: number; playedToday: number; users: number};
        recent: {icon: string; text: string; at: string}[];
    };
    links: {pulse: string; horizon: string; createParty: string};
}
