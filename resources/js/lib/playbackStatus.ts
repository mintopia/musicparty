import type {DashboardControl, DashboardIssue, DashboardIssueAction, LegacyPartyState} from '@/types';

export type PlaybackStatusKind = 'playing' | 'paused' | 'idle' | 'outside' | 'no_device' | 'stale' | 'relink';
export type PlaybackStatusTone = 'playing' | 'paused' | 'idle' | 'warn' | 'stale' | 'error';
export type PlaybackStatusAction = Exclude<DashboardIssueAction, 'activate'>;

export const ACTION_LABELS: Record<PlaybackStatusAction, string> = {
    open_web_player: 'Open Web Player',
    start: 'Start the party',
    relink: 'Re-link Spotify',
    refresh_devices: 'Refresh devices',
};

const ISSUE_CODES: Partial<Record<PlaybackStatusKind, DashboardIssue['code']>> = {
    relink: 'relink_required',
    no_device: 'no_device',
    stale: 'player_stale',
    outside: 'outside',
};

export interface PlaybackStatus {
    kind: PlaybackStatusKind;
    tone: PlaybackStatusTone;
    headline: string;
    detail: string;
    action: PlaybackStatusAction | null;
    attention: {fact: string; detail: string} | null;
    age: {text: string; stale: boolean; words: string};
}

export interface PlaybackStatusInput {
    control: DashboardControl;
    state: LegacyPartyState;
    host: string;
    hasWebPlayer: boolean;
    now: number;
}

export const STALE_SECONDS = 60;

export function formatAge(seconds: number): string {
    const s = Math.max(0, Math.floor(seconds));
    if (s < 1) {
        return 'just now';
    }
    const units: [number, string][] = [
        [365 * 86400, 'year'],
        [86400, 'day'],
        [3600, 'hour'],
        [60, 'minute'],
        [1, 'second'],
    ];
    const [size, name] = units.find(([unit]) => s >= unit) ?? units[units.length - 1];
    const count = Math.floor(s / size);
    return `${count} ${name}${count === 1 ? '' : 's'} ago`;
}

export function derivePlaybackStatus({control, state, host, hasWebPlayer, now}: PlaybackStatusInput): PlaybackStatus {
    const {health, start, playingFrom} = control;
    const live = health.player.connected === true;
    const ageSeconds = health.receivedAt === null ? null : Math.max(0, (now - Date.parse(health.receivedAt)) / 1000);
    const ageWords = ageSeconds === null ? 'Never' : formatAge(ageSeconds);
    const stale = !live && ageSeconds !== null && ageSeconds > STALE_SECONDS;
    const age = {text: live ? 'Live' : stale ? `${ageWords} · stale` : ageWords, stale, words: ageWords};
    const device = health.device.name ?? 'your device';
    const player = health.player.label;
    const webPlayerAction = hasWebPlayer ? 'open_web_player' : null;
    const track = state.now;
    const lastHeard = track
        ? `${track.name} by ${track.artists.map((a) => a.name).join(', ')}, ${ageWords}`
        : ageWords.toLowerCase();

    const make = (
        kind: PlaybackStatusKind,
        tone: PlaybackStatusTone,
        headline: string,
        detail: string,
        action: PlaybackStatusAction | null,
        attention: PlaybackStatus['attention'] = null,
    ): PlaybackStatus => ({kind, tone, headline, detail, action, attention, age});

    if (health.link === 'relink_required' || start.state === 'relink_required') {
        return make(
            'relink',
            'error',
            'Re-link needed',
            'Playback is stopped until the Spotify link is renewed.',
            'relink',
            {
                fact: 'Re-link needed',
                detail: `${host}'s Spotify link has expired, so Music Party can't control playback.`,
            },
        );
    }
    if (start.state === 'no_device') {
        return make(
            'no_device',
            'error',
            'No device',
            'Open Spotify on a device, then refresh devices.',
            'refresh_devices',
            {
                fact: 'No device',
                detail: 'Open Spotify on the DJ laptop or wake the Encore speaker, then refresh.',
            },
        );
    }
    if (stale) {
        return make('stale', 'stale', 'Player stale', `Last heard: ${lastHeard}.`, webPlayerAction, {
            fact: 'Player stale',
            detail: `${player} stopped reporting ${ageWords}.${hasWebPlayer ? ' Open the Web Player to keep the party going.' : ''}`,
        });
    }
    if (playingFrom.kind === 'outside' || start.state === 'outside') {
        const label = playingFrom.label ?? 'something else';
        return make(
            'outside',
            'warn',
            'Outside the party',
            `Spotify is playing ${label} on ${device}. Requests won't play until the party starts.`,
            'start',
            {
                fact: 'Outside the party',
                detail: `Spotify is playing ${label}. Requests won't play until you start the party.`,
            },
        );
    }
    if (track !== null && state.status?.isPlaying === true) {
        const detail = live
            ? `${player} is reporting live from ${device}.`
            : `${player} reported ${ageWords} from ${device}.`;
        return make('playing', 'playing', 'Playing', detail, null);
    }
    if (track !== null) {
        return make('paused', 'paused', 'Paused', `Paused on ${device}.`, null);
    }
    return make('idle', 'idle', 'Nothing playing', 'Pick a device and start the party.', 'start');
}

export function attentionIssue(status: PlaybackStatus): DashboardIssue | null {
    const code = ISSUE_CODES[status.kind];
    if (!code || !status.attention) {
        return null;
    }
    return {
        code,
        ...status.attention,
        action: status.action ? {label: ACTION_LABELS[status.action], kind: status.action} : null,
    };
}
