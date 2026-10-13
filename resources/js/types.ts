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
