export type SpotifyTrack = {
    name: string;
    uri: string;
    artists: Array<{name: string}>;
    album?: {name: string; images?: Array<{url: string}>};
};
export type SpotifyState = {
    paused: boolean;
    position: number;
    duration: number;
    timestamp: number;
    shuffle: boolean;
    repeat_mode: 0 | 1 | 2;
    track_window: {current_track: SpotifyTrack};
};
export type SpotifyPlayer = {
    addListener(event: string, cb: (payload: never) => void): boolean;
    connect(): Promise<boolean>;
    disconnect(): void;
    activateElement(): Promise<void>;
    togglePlay(): Promise<void>;
    resume(): Promise<void>;
    pause(): Promise<void>;
    nextTrack(): Promise<void>;
    previousTrack(): Promise<void>;
    seek(positionMs: number): Promise<void>;
};
export type SpotifyGlobal = {
    Player: new (options: {
        name: string;
        getOAuthToken: (cb: (token: string) => void) => void;
        volume: number;
    }) => SpotifyPlayer;
};
export type WhisperChannel = {
    listen<T>(event: string, cb: (payload: T) => void): unknown;
    listenForWhisper<T>(event: string, cb: (payload: T) => void): unknown;
    whisper(event: string, data: Record<string, unknown>): unknown;
};
