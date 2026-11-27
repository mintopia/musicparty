import {describe, expect, it} from 'vitest';
import {attentionIssue, derivePlaybackStatus, formatAge, type PlaybackStatusInput} from '@/lib/playbackStatus';
import type {DashboardControl, LegacyPartyState} from '@/types';

const NOW = Date.parse('2026-01-01T12:00:00Z');
const ago = (seconds: number) => new Date(NOW - seconds * 1000).toISOString();

interface Scenario {
    start?: DashboardControl['start']['state'];
    link?: DashboardControl['health']['link'];
    connected?: boolean | null;
    receivedAgo?: number | null;
    outside?: boolean;
    track?: boolean;
    playing?: boolean;
    webPlayer?: boolean;
}

function input(s: Scenario = {}): PlaybackStatusInput {
    const track = s.track ?? true;
    const state = {
        now: track ? {name: 'Sandstorm', artists: [{name: 'Darude'}]} : null,
        next: null,
        current: null,
        status: {isPlaying: s.playing ?? true, sampledAt: ago(1), progressMs: 0, length: 1000},
    } as unknown as LegacyPartyState;
    const control = {
        playingFrom: {kind: s.outside ? 'outside' : 'party', label: s.outside ? 'Liked Songs' : null},
        nowPlaying: {isRequest: true, isFallback: false, requestedBy: null},
        health: {
            player: {
                source: 'encore',
                label: 'Encore',
                fresh: true,
                connected: s.connected === undefined ? true : s.connected,
            },
            device: {id: 'd1', name: 'Main Hall Speaker'},
            receivedAt: s.receivedAgo === null ? null : ago(s.receivedAgo ?? 2),
            link: s.link ?? 'connected',
            polling: 'off',
        },
        start: {state: s.start ?? 'hidden', target: null, devices: [], selectedDeviceId: null},
    } as DashboardControl;
    return {control, state, host: 'Jess', hasWebPlayer: s.webPlayer ?? true, now: NOW};
}

const kind = (s: Scenario) => derivePlaybackStatus(input(s)).kind;

describe('derivePlaybackStatus', () => {
    it.each([
        ['playing', {}, 'Playing'],
        ['paused', {playing: false}, 'Paused'],
        ['idle', {track: false, start: 'idle' as const}, 'Nothing playing'],
        ['outside', {outside: true, start: 'outside' as const}, 'Outside the party'],
        ['no_device', {start: 'no_device' as const, track: false}, 'No device'],
        ['stale', {connected: null, receivedAgo: 180}, 'Player stale'],
        ['relink', {link: 'relink_required' as const}, 'Re-link needed'],
    ])('derives %s', (expected, scenario, headline) => {
        const status = derivePlaybackStatus(input(scenario as Scenario));

        expect(status.kind).toBe(expected);
        expect(status.headline).toBe(headline);
    });

    it('ranks Re-link needed above No device above Player stale', () => {
        const everything = {connected: null, receivedAgo: 4000, start: 'no_device' as const};

        expect(kind({...everything, link: 'relink_required'})).toBe('relink');
        expect(kind(everything)).toBe('no_device');
        expect(kind({connected: null, receivedAgo: 4000})).toBe('stale');
    });

    it('treats a connected player as live however old the last frame is', () => {
        const status = derivePlaybackStatus(input({connected: true, receivedAgo: 9999}));

        expect(status.kind).toBe('playing');
        expect(status.age).toMatchObject({text: 'Live', stale: false});
    });

    it('gives a next-step action for every non-playing state', () => {
        expect(derivePlaybackStatus(input({playing: false})).action).toBeNull();
        expect(derivePlaybackStatus(input({track: false, start: 'idle'})).action).toBe('start');
        expect(derivePlaybackStatus(input({outside: true, start: 'outside'})).action).toBe('start');
        expect(derivePlaybackStatus(input({start: 'no_device'})).action).toBe('refresh_devices');
        expect(derivePlaybackStatus(input({link: 'relink_required'})).action).toBe('relink');
        expect(derivePlaybackStatus(input({connected: null, receivedAgo: 180})).action).toBe('open_web_player');
        expect(derivePlaybackStatus(input({connected: null, receivedAgo: 180, webPlayer: false})).action).toBeNull();
    });

    it('writes ages in words and never in raw minutes', () => {
        const stale = derivePlaybackStatus(input({connected: null, receivedAgo: 180}));
        const old = derivePlaybackStatus(input({connected: null, receivedAgo: 45 * 86400}));

        expect(stale.age.text).toBe('3 minutes ago · stale');
        expect(stale.detail).toBe('Last heard: Sandstorm by Darude, 3 minutes ago.');
        expect(old.age.text).toBe('45 days ago · stale');
        expect(old.attention?.detail).toContain('45 days ago');
        expect(JSON.stringify(old)).not.toMatch(/\d+ min\b/);
    });

    it('names the player and its last report in the paused detail', () => {
        const paused = derivePlaybackStatus(input({playing: false, receivedAgo: 4}));

        expect(paused.detail).toMatch(/^Paused on .+\. .+ last reported 4 seconds ago\.$/);
    });

    it('words the stale panel message with the player name and age', () => {
        const stale = derivePlaybackStatus(input({connected: null, receivedAgo: 180}));
        const noWebPlayer = derivePlaybackStatus(input({connected: null, receivedAgo: 180, webPlayer: false}));

        expect(stale.panelMessage).toMatch(
            /^.+ stopped reporting 3 minutes ago, so Music Party can't tell what is playing\. Open the Web Player or check the .+ speaker\.$/,
        );
        expect(noWebPlayer.panelMessage).toContain('Check the');
        expect(noWebPlayer.panelMessage).not.toContain('Web Player');
    });

    it('says Never when nothing has been received', () => {
        expect(derivePlaybackStatus(input({connected: null, receivedAgo: null})).age.text).toBe('Never');
    });

    it('turns only the playback problems into an attention item', () => {
        expect(attentionIssue(derivePlaybackStatus(input()))).toBeNull();
        expect(attentionIssue(derivePlaybackStatus(input({playing: false})))).toBeNull();
        expect(attentionIssue(derivePlaybackStatus(input({start: 'no_device'})))).toMatchObject({
            code: 'no_device',
            fact: 'No device',
            action: {label: 'Refresh devices', kind: 'refresh_devices'},
        });
    });
});

describe('formatAge', () => {
    it.each([
        [0, 'just now'],
        [1, '1 second ago'],
        [4, '4 seconds ago'],
        [59, '59 seconds ago'],
        [60, '1 minute ago'],
        [180, '3 minutes ago'],
        [3600, '1 hour ago'],
        [7200, '2 hours ago'],
        [64800 * 60, '45 days ago'],
        [400 * 86400, '1 year ago'],
    ])('formats %i seconds as %s', (seconds, text) => {
        expect(formatAge(seconds)).toBe(text);
    });
});
