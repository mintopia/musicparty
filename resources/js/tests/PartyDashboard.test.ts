import {flushPromises, mount} from '@vue/test-utils';
import axios, {AxiosError} from 'axios';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {setEchoFactory} from '@/composables/useEcho';
import Dashboard from '@/pages/Parties/Dashboard.vue';
import type {
    DashboardIssue,
    DashboardProps,
    DashboardRequest,
    DashboardStartState,
    DashboardDevice,
    FlaggedRequest,
    LegacyTrack,
} from '@/types';

const inertia = vi.hoisted(() => ({reload: vi.fn()}));

vi.mock('@inertiajs/vue3', () => ({
    Head: {render: () => null},
    router: inertia,
    usePage: () => ({props: {reverb: {}, flash: {}}, url: '/'}),
}));
vi.mock('@/layouts/AppLayout.vue', () => ({
    default: {template: '<div><slot name="breadcrumbs" /><slot name="header" /><slot /></div>'},
}));
vi.mock('@/routes', () => ({home: () => ({url: '/'})}));

const echo = vi.hoisted(() => ({
    handlers: {} as Record<string, (payload?: never) => void>,
    states: [] as Array<(states: {previous: string; current: string}) => void>,
}));

const originalAdapter = axios.defaults.adapter;
let calls: {method?: string; url?: string; data?: string}[] = [];
let responder: (url: string, method: string) => Promise<unknown> = () => Promise.resolve({});

function failure(status: number, data: Record<string, unknown>) {
    return (config: never) =>
        Promise.reject(
            new AxiosError('failed', 'ERR_BAD_RESPONSE', config, null, {
                status,
                data,
                statusText: '',
                headers: {},
                config,
            } as never),
        );
}

function respondWith(url: string, handler: (config: never) => Promise<unknown>): void {
    const previous = responder;
    responder = (target, method) => (target === url ? Promise.resolve(handler as never) : previous(target, method));
}

beforeEach(() => {
    calls = [];
    responder = () => Promise.resolve({});
    inertia.reload.mockReset();
    echo.handlers = {};
    echo.states = [];
    const channel = {
        listen(event: string, cb: (payload?: never) => void) {
            echo.handlers[event] = cb;
            return channel;
        },
        stopListening: () => channel,
    };
    setEchoFactory(() => ({
        private: () => channel,
        channel: () => channel,
        leave: () => undefined,
        connector: {
            pusher: {
                connection: {
                    bind: (_event: string, cb: (states: {previous: string; current: string}) => void) =>
                        echo.states.push(cb),
                    unbind: () => undefined,
                },
            },
        },
    }));
    axios.defaults.adapter = async (config) => {
        calls.push({method: config.method, url: config.url, data: config.data});
        const outcome = await responder(config.url ?? '', config.method ?? 'get');
        if (typeof outcome === 'function') {
            return (outcome as (c: unknown) => Promise<unknown>)(config) as never;
        }
        return {data: outcome, status: 200, statusText: 'OK', headers: {}, config} as never;
    };
});

afterEach(() => {
    axios.defaults.adapter = originalAdapter;
    setEchoFactory(null);
    vi.useRealTimers();
});

function track(name: string, extra: Partial<LegacyTrack> = {}): LegacyTrack {
    return {
        name,
        artists: [{name: 'Artist'}],
        album: {name: 'Album', image_url: 'https://img.test/a.jpg'},
        length: 222000,
        score: 3,
        user: 'pixelpanda',
        fallback_name: null,
        css_classes: '',
        ...extra,
    };
}

function request(id: number, name: string, sent = false): DashboardRequest {
    return {
        ...track(name),
        id,
        vote: 0,
        created_at: '2026-01-01T00:00:00Z',
        sent,
    };
}

function device(id: string, extra: Partial<DashboardDevice> = {}): DashboardDevice {
    return {
        id,
        name: `Device ${id}`,
        type: 'Computer',
        isActive: false,
        isRestricted: false,
        label: 'Computer',
        icon: 'ti-device-laptop',
        ...extra,
    };
}

function flagged(id: number, name: string): FlaggedRequest {
    return {
        id,
        reason: null,
        flagged_at: '2026-01-01T00:00:00Z',
        requester: {id: 1, name: 'Boris'},
        ai_review_url: null,
        track: {
            provider_id: 'spotify',
            id: `t${id}`,
            name,
            duration_ms: 1000,
            explicit: false,
            external_url: null,
            provider_name: 'Spotify',
            album: {id: 'a', name: 'A', image_url: null},
            artists: [{id: 'x', name: 'Wham!'}],
            queue: {in_queue: false, upcoming_id: null, score: 0, vote: null, requested_by: null},
            eligibility: {allowed: true, outcome: 'flag', reason: null},
        },
    };
}

interface Options {
    state?: DashboardStartState;
    devices?: DashboardDevice[];
    target?: DashboardProps['control']['start']['target'];
    playingFrom?: DashboardProps['control']['playingFrom'];
    issues?: DashboardIssue[];
    flagged?: FlaggedRequest[];
    moderate?: boolean;
    webPlayer?: string | null;
    upNext?: DashboardRequest[];
    upcoming?: DashboardRequest[];
    connected?: boolean | null;
    receivedAt?: string;
    nowPlaying?: DashboardProps['control']['nowPlaying'];
    nothingPlaying?: boolean;
    paused?: boolean;
    link?: 'connected' | 'relink_required';
    myRating?: DashboardProps['myRating'];
}

function makeProps(options: Options = {}): DashboardProps {
    const state = options.state ?? 'hidden';
    const items = options.flagged ?? [];
    return {
        party: {code: 'ABCD', name: 'Main Hall', active: true, weighted: false, downvotes: true, canManage: true},
        host: {name: 'Kestrel'},
        can: {control: true, moderate: options.moderate ?? true, player: true},
        timezone: 'UTC',
        playerState: {
            now: options.nothingPlaying ? null : {...track('Sandstorm'), artists: [{name: 'Darude'}]},
            next: null,
            current: {id: 9, user: 'Tamsin', rating: 0},
            status: {
                isPlaying: !options.paused,
                sampledAt: new Date().toISOString(),
                progressMs: 112000,
                length: 225000,
            },
        },
        control: {
            playingFrom: options.playingFrom ?? {kind: state === 'hidden' ? 'party' : 'nothing', label: null},
            nowPlaying: options.nowPlaying ?? {isRequest: true, isFallback: false, requestedBy: 'Tamsin'},
            health: {
                player: {
                    source: 'encore',
                    label: 'Encore',
                    fresh: true,
                    connected: options.connected === undefined ? true : options.connected,
                },
                device: {id: 'd1', name: 'Main Hall Speaker'},
                receivedAt: options.receivedAt ?? new Date().toISOString(),
                link: options.link ?? 'connected',
                polling: 'backup',
            },
            start: {
                state,
                target: options.target === undefined ? target() : options.target,
                devices: options.devices ?? [device('d1', {isActive: true}), device('d2')],
                selectedDeviceId: 'd1',
            },
        },
        upNext: options.upNext ?? [request(1, 'Mr. Brightside', true), request(2, 'Levels'), request(3, 'Bangarang')],
        upcoming: {items: options.upcoming ?? [request(4, 'Kernkraft 400')], total: 24},
        attention: {issues: options.issues ?? [], flagged: items, flaggedTotal: items.length},
        links: {
            queue: '/q',
            songs: '/songs',
            flagged: '/flagged',
            settings: '/settings',
            members: '/members',
            moderation: '/moderation',
            mods: '/mods',
            tv: '/tv',
            webPlayer: options.webPlayer === undefined ? '/player' : options.webPlayer,
            encoreRelay: '/settings#encore-relay',
            relink: '/profile/accounts/spotify/link',
            join: 'https://mp.test/parties/ABCD',
            fallbackSettings: '/settings#fallback',
        },
        hasFallbackPlaylist: true,
        myRating: options.myRating,
    };
}

function target(kind: 'resume' | 'next' | 'select' = 'select', positionMs = 0) {
    return {kind, track: track('Mr. Brightside'), positionMs, requestedBy: 'fragmaster'};
}

const mountPage = (options: Options = {}) => mount(Dashboard, {props: makeProps(options)});
const post = (url: string) => calls.filter((call) => call.method === 'post' && call.url === url);

describe('control room strip', () => {
    it('shows only the empty state when nothing is playing, leaving Start Playback as the action', () => {
        const wrapper = mountPage({state: 'idle', nothingPlaying: true});

        expect(wrapper.find('[data-test="status-headline"]').text()).toBe('Nothing playing');
        expect(wrapper.find('[data-test="play-pause"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="rating"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="start-panel"]').exists()).toBe(true);
    });

    it('labels a playing fallback track instead of naming a requester', () => {
        const wrapper = mountPage({nowPlaying: {isRequest: false, isFallback: true, requestedBy: null}});

        expect(wrapper.get('[data-test="now-origin"]').text()).toBe('Fallback Track');
    });

    it('shows the Up Next deck while the party is playing', () => {
        const wrapper = mountPage({state: 'hidden'});

        expect(wrapper.find('[data-test="up-next-deck"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="restart-in-party"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="start-panel"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="up-next-card"]').exists()).toBe(false);
    });

    it('shows the Start Playback panel, when not playing in the party', () => {
        const wrapper = mountPage({state: 'idle'});

        expect(wrapper.find('[data-test="start-panel"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="up-next-deck"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="restart-in-party"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="up-next-card"]').exists()).toBe(false);
        expect(wrapper.findAll('[data-test="upcoming-row"]')).toHaveLength(1);
    });

    it('labels the resume button plainly and keeps the track and position in the message', () => {
        const wrapper = mountPage({state: 'resume', target: target('resume', 72000), nothingPlaying: true});

        expect(wrapper.find('[data-test="start-button"]').text()).toBe('Resume');
        expect(wrapper.find('[data-test="start-message"]').text()).toContain('Mr. Brightside at 1:12');
        expect(wrapper.find('[data-test="start-badge"]').text()).toBe('Nothing playing');
    });

    it('posts the selected device when starting, and disables restricted devices', async () => {
        const wrapper = mountPage({
            state: 'idle',
            devices: [device('d1', {isActive: true}), device('d2'), device('d3', {isRestricted: true})],
        });

        expect((wrapper.find('[data-test="device-d3"]').element as HTMLInputElement).disabled).toBe(true);
        await wrapper.find('[data-test="device-d2"]').setValue(true);
        await wrapper.find('[data-test="start-button"]').trigger('click');
        await flushPromises();

        expect(post('/api/v1/parties/ABCD/playback/start')).toHaveLength(1);
        expect(JSON.parse(post('/api/v1/parties/ABCD/playback/start')[0].data ?? '{}')).toMatchObject({deviceId: 'd2'});
        expect(inertia.reload).toHaveBeenCalledWith({only: ['control', 'upNext', 'upcoming', 'attention']});
    });

    it('shows a spinner and locks devices while starting', async () => {
        let release: (value: unknown) => void = () => undefined;
        responder = () => new Promise((resolve) => (release = resolve));
        const wrapper = mountPage({state: 'idle'});

        await wrapper.find('[data-test="start-button"]').trigger('click');

        expect(wrapper.find('[data-test="start-spinner"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="start-button"]').text()).toContain("Starting on Kestrel's Spotify");
        expect((wrapper.find('[data-test="device-d2"]').element as HTMLInputElement).disabled).toBe(true);
        expect(wrapper.find('[data-test="start-badge"]').text()).toBe('Starting');

        release({});
        await flushPromises();
        expect(wrapper.find('[data-test="start-spinner"]').exists()).toBe(false);
    });

    it.each([
        ['premium_required', 409, 'Premium required'],
        ['no_device', 409, 'No device'],
        ['relink_required', 409, 'Re-link needed'],
    ])('renders the %s state from a failed start', async (code, status, badge) => {
        respondWith('/api/v1/parties/ABCD/playback/start', failure(status, {message: 'nope', code}) as never);
        const wrapper = mountPage({state: 'idle', nothingPlaying: true});

        await wrapper.find('[data-test="start-button"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-test="start-badge"]').text()).toBe(badge);
    });

    it('counts down after a rate limit and disables the button', async () => {
        vi.useFakeTimers({toFake: ['setInterval', 'clearInterval']});
        respondWith(
            '/api/v1/parties/ABCD/playback/start',
            failure(503, {message: 'busy', code: 'rate_limited', retryAfter: 3}) as never,
        );
        const wrapper = mountPage({state: 'idle'});

        await wrapper.find('[data-test="start-button"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-test="start-message"]').text()).toContain('try again in 3 s');
        expect((wrapper.find('[data-test="start-button"]').element as HTMLButtonElement).disabled).toBe(true);

        await vi.advanceTimersByTimeAsync(3000);
        expect(wrapper.find('[data-test="start-message"]').text()).not.toContain('try again');
        expect((wrapper.find('[data-test="start-button"]').element as HTMLButtonElement).disabled).toBe(false);
    });

    it('shows the skip hint only when playing outside the party', () => {
        const outside = mountPage({state: 'outside', playingFrom: {kind: 'outside', label: "Kestrel's Liked Songs"}});
        expect(outside.find('[data-test="skip-hint"]').text()).toContain("Skip moves through Kestrel's Liked Songs");
        expect(outside.find('[data-test="start-message"]').text()).toContain("Kestrel's Liked Songs");

        expect(mountPage({state: 'hidden'}).find('[data-test="skip-hint"]').exists()).toBe(false);
        expect(mountPage({state: 'idle'}).find('[data-test="skip-hint"]').exists()).toBe(false);
    });
});

describe('player unit', () => {
    const up = (wrapper: ReturnType<typeof mountPage>) => wrapper.get('[data-test="rate-up"]');
    const down = (wrapper: ReturnType<typeof mountPage>) => wrapper.get('[data-test="rate-down"]');
    const count = (wrapper: ReturnType<typeof mountPage>) => wrapper.get('[data-test="rating"]').text();

    it('orders the rating control up, count, down and presses the thumb for the own vote', () => {
        const wrapper = mountPage({myRating: {playedSongId: 9, value: -1}});
        const parts = [...wrapper.get('[data-test="rate-control"]').element.children].map(
            (el) => el.getAttribute('aria-label') ?? el.getAttribute('data-test'),
        );

        expect(parts).toEqual(['Rate up', 'rating', 'Rate down']);
        expect(down(wrapper).attributes('aria-pressed')).toBe('true');
        expect(up(wrapper).attributes('aria-pressed')).toBe('false');
        expect(
            mountPage({myRating: {playedSongId: 8, value: 1}})
                .get('[data-test="rate-up"]')
                .attributes('aria-pressed'),
        ).toBe('false');
        expect(mountPage().get('[data-test="rate-up"]').attributes('aria-pressed')).toBe('false');
    });

    it('rates optimistically with set semantics and keeps the buttons enabled', async () => {
        const wrapper = mountPage();

        await up(wrapper).trigger('click');
        expect(count(wrapper)).toBe('1');
        expect(up(wrapper).attributes('aria-pressed')).toBe('true');
        expect(up(wrapper).attributes('disabled')).toBeUndefined();
        await flushPromises();
        expect(JSON.parse(post('/api/v1/parties/ABCD/playedsongs/9/rate')[0]?.data ?? '{}')).toEqual({rating: 1});

        await up(wrapper).trigger('click');
        await flushPromises();
        expect(JSON.parse(post('/api/v1/parties/ABCD/playedsongs/9/rate')[1]?.data ?? '{}')).toEqual({rating: 0});
        expect(count(wrapper)).toBe('0');
    });

    it('rolls back and tells the host when the rating cannot be saved', async () => {
        respondWith('/api/v1/parties/ABCD/playedsongs/9/rate', failure(429, {}) as never);
        const wrapper = mountPage();

        await up(wrapper).trigger('click');
        await flushPromises();

        expect(count(wrapper)).toBe('0');
        expect(up(wrapper).attributes('aria-pressed')).toBe('false');
        expect(wrapper.get('[data-test="notice"]').text()).toContain('Slow down, try again in a moment');
    });

    it('freezes the seek bar at the last position while paused', async () => {
        vi.useFakeTimers({toFake: ['setInterval', 'clearInterval', 'Date']});
        const wrapper = mountPage({paused: true});
        const before = wrapper.get('[data-test="seek"]').attributes('aria-valuetext');

        await vi.advanceTimersByTimeAsync(20000);

        expect(before).toBe('1:52');
        expect(wrapper.get('[data-test="seek"]').attributes('aria-valuetext')).toBe('1:52');
    });

    it('shows a Reconnecting pill while the websocket is down and drops the icons from the metadata rows', async () => {
        const wrapper = mountPage();
        expect(wrapper.find('[data-test="reconnecting"]').exists()).toBe(false);

        echo.states.forEach((cb) => cb({previous: 'connected', current: 'connected'}));
        echo.states.forEach((cb) => cb({previous: 'connected', current: 'unavailable'}));
        await wrapper.vm.$nextTick();

        expect(wrapper.get('[data-test="reconnecting"]').text()).toBe('Reconnecting…');
        expect(
            wrapper
                .find('[data-test="now-artists"] .ti, [data-test="now-album"] .ti, [data-test="now-origin"] .ti')
                .exists(),
        ).toBe(false);
    });
});

describe('health grid and upcoming list', () => {
    it('shows Live without a stale warning while Encore is connected', () => {
        const wrapper = mountPage({connected: true, receivedAt: new Date(Date.now() - 600000).toISOString()});

        expect(wrapper.find('[data-test="health-age"]').text()).toBe('Live');
        expect(wrapper.find('[data-test="health-from"]').exists()).toBe(false);
    });

    it('shows the age from the last frame, with a stale warning, when not connected', () => {
        const wrapper = mountPage({connected: null, receivedAt: new Date(Date.now() - 600000).toISOString()});

        expect(wrapper.find('[data-test="health-age"]').text()).toContain('stale');
    });

    it('shows a sent upcoming row with a badge instead of voting and delete', () => {
        const wrapper = mountPage({state: 'idle', upcoming: [request(4, 'Sent one', true), request(5, 'Other')]});
        const rows = wrapper.findAll('[data-test="upcoming-row"]');

        expect(rows[0].find('[data-test="sent-badge"]').exists()).toBe(true);
        expect(rows[0].find('[data-test="upvote"]').exists()).toBe(false);
        expect(rows[0].find('[data-test="remove"]').exists()).toBe(false);
        expect(rows[1].find('[data-test="upvote"]').exists()).toBe(true);
    });
});

describe('up next deck', () => {
    it('skips for the first row and plays the others now', async () => {
        const wrapper = mountPage({state: 'hidden'});
        const buttons = wrapper.findAll('[data-test="play-now"]');

        expect(buttons[0].attributes('title')).toBe('Next track: skip to this now');
        expect(buttons[1].attributes('title')).toBe('Play now: move to the top of the queue and play');

        await buttons[0].trigger('click');
        await flushPromises();
        expect(JSON.parse(post('/api/v1/parties/ABCD/control')[0].data ?? '{}')).toEqual({action: 'next'});

        await buttons[1].trigger('click');
        await flushPromises();
        expect(post('/api/v1/parties/ABCD/upcomingsongs/2/play')).toHaveLength(1);
    });

    it('plays an unsent first row through the play endpoint, not next', async () => {
        const wrapper = mountPage({state: 'hidden', upNext: [request(5, 'Unsent'), request(6, 'Other')]});

        expect(wrapper.find('[data-test="play-now"]').attributes('title')).toBe(
            'Play now: move to the top of the queue and play',
        );
        await wrapper.find('[data-test="play-now"]').trigger('click');
        await flushPromises();

        expect(post('/api/v1/parties/ABCD/upcomingsongs/5/play')).toHaveLength(1);
        expect(post('/api/v1/parties/ABCD/control')).toHaveLength(0);
    });

    it('disables Delete on a sent row with an explanatory tooltip', () => {
        const wrapper = mountPage({state: 'hidden'});
        const rows = wrapper.findAll('[data-test="up-next-row"]');

        expect(rows[0].find('[data-test="delete-sent-tip"]').attributes('title')).toBe(
            "Already sent to Spotify, so it can't be removed",
        );
        expect(rows[0].find('[data-test="delete-sent-tip"]').attributes('tabindex')).toBe('0');
        expect((rows[0].find('[data-test="delete"]').element as HTMLButtonElement).disabled).toBe(true);
        expect((rows[1].find('[data-test="delete"]').element as HTMLButtonElement).disabled).toBe(false);
    });

    it('confirms before deleting and makes no call on cancel', async () => {
        const wrapper = mountPage({state: 'hidden'});

        await wrapper.findAll('[data-test="up-next-row"]')[1].find('[data-test="delete"]').trigger('click');
        expect(wrapper.find('[data-test="delete-dialog"]').text()).toContain(
            'Levels by Artist, requested by pixelpanda, will leave the queue and its 3 votes are lost.',
        );

        await wrapper.find('[data-test="delete-cancel"]').trigger('click');
        expect(wrapper.find('[data-test="delete-dialog"]').exists()).toBe(false);
        expect(calls.filter((call) => call.method === 'delete')).toHaveLength(0);

        await wrapper.findAll('[data-test="up-next-row"]')[1].find('[data-test="delete"]').trigger('click');
        await wrapper.find('[data-test="delete-confirm"]').trigger('click');
        await flushPromises();

        expect(calls.filter((call) => call.method === 'delete').map((call) => call.url)).toEqual([
            '/api/v1/parties/ABCD/upcomingsongs/2',
        ]);
        expect(wrapper.find('[data-test="delete-dialog"]').exists()).toBe(false);
    });

    it('disables Delete when the viewer cannot moderate', () => {
        const wrapper = mountPage({state: 'hidden', moderate: false});

        expect(
            (wrapper.findAll('[data-test="up-next-row"]')[1].find('[data-test="delete"]').element as HTMLButtonElement)
                .disabled,
        ).toBe(true);
        expect((wrapper.find('[data-test="remove"]').element as HTMLButtonElement).disabled).toBe(true);
    });
});

describe('needs attention', () => {
    it('lists issues before the collapsible flagged group', async () => {
        const wrapper = mountPage({
            issues: [
                {
                    code: 'party_inactive',
                    fact: 'Party inactive',
                    detail: 'Nothing is sent to Spotify',
                    action: {label: 'Activate', kind: 'activate'},
                },
            ],
            flagged: [flagged(1, 'Last Christmas'), flagged(2, 'Axel F')],
        });
        const card = wrapper.find('[data-test="needs-attention"]');

        expect(card.find('[data-test="attention-count"]').text()).toBe('3');
        expect(card.find('.na-body').element.firstElementChild?.getAttribute('data-test')).toBe('issue-row');
        expect(card.find('[data-test="flagged-toggle"]').text()).toContain('2 Flagged Requests');
        expect(card.findAll('[data-test="flagged-row"]')).toHaveLength(2);

        await card.find('[data-test="flagged-toggle"]').trigger('click');
        expect(card.find('[data-test="flagged-toggle"]').attributes('aria-expanded')).toBe('false');
        expect(card.findAll('[data-test="flagged-row"]')).toHaveLength(0);

        await card.find('[data-test="issue-action"]').trigger('click');
        await flushPromises();
        expect(post('/api/v1/parties/ABCD/activate')).toHaveLength(1);
    });

    it('links flagged requests to their AI review when one exists', () => {
        const linked = {...flagged(1, 'Last Christmas'), ai_review_url: '/parties/ABCD/ai-reviews/5'};
        const wrapper = mountPage({flagged: [linked, flagged(2, 'Axel F')]});

        const rows = wrapper.findAll('[data-test="flagged-row"]');
        expect(rows[0].find('[data-test="ai-review-link"]').attributes('href')).toBe('/parties/ABCD/ai-reviews/5');
        expect(rows[1].find('[data-test="ai-review-link"]').exists()).toBe(false);
    });

    it('approves and rejects flagged requests', async () => {
        const wrapper = mountPage({flagged: [flagged(7, 'Last Christmas')]});

        expect(wrapper.find('[data-test="approve"]').attributes('aria-label')).toBe('Approve Last Christmas');
        await wrapper.find('[data-test="approve"]').trigger('click');
        await flushPromises();
        await wrapper.find('[data-test="reject"]').trigger('click');
        await flushPromises();

        expect(post('/parties/ABCD/flagged/7/approve')).toHaveLength(1);
        expect(post('/parties/ABCD/flagged/7/reject')).toHaveLength(1);
    });

    it('hides approve and reject without moderation rights', () => {
        const wrapper = mountPage({flagged: [flagged(7, 'Last Christmas')], moderate: false});

        expect(wrapper.find('[data-test="flagged-row"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="approve"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="reject"]').exists()).toBe(false);
    });

    it('collapses to All clear when there is nothing to do', () => {
        const wrapper = mountPage();

        expect(wrapper.find('[data-test="all-clear"]').text()).toContain('All clear');
        expect(wrapper.find('[data-test="needs-attention"] .na-body').exists()).toBe(false);
    });
});

describe('stale player issue', () => {
    const stale = {code: 'player_stale' as const, fact: 'Player not reporting', detail: 'Polling is off', action: null};

    const longAgo = () => new Date(Date.now() - 600000).toISOString();

    it('is hidden while Encore is connected, even if the attention data is older', () => {
        const wrapper = mountPage({connected: true, issues: [stale], receivedAt: longAgo()});

        expect(wrapper.findAll('[data-test="issue-row"]')).toHaveLength(0);
    });

    it('is shown once, from the derived status, when the player has gone quiet', () => {
        const wrapper = mountPage({connected: null, issues: [stale], receivedAt: longAgo()});
        const rows = wrapper.findAll('[data-test="issue-row"]');

        expect(rows).toHaveLength(1);
        expect(rows[0].text()).toContain('stopped reporting 10 minutes ago');
        expect(rows[0].find('[data-test="issue-action"]').attributes('href')).toBe('/player');
    });
});

describe('one playback status', () => {
    const longAgo = () => new Date(Date.now() - 600000).toISOString();

    it('makes the status headline the first h2 and names it in the badge and attention item', () => {
        const wrapper = mountPage({state: 'idle', connected: null, receivedAt: longAgo()});

        expect(wrapper.find('h2').attributes('data-test')).toBe('status-headline');
        expect(wrapper.get('[data-test="status-headline"]').text()).toBe('Player stale');
        expect(wrapper.get('[data-test="start-badge"]').text()).toBe('Player stale');
        expect(wrapper.get('[data-test="issue-row"]').text()).toContain('Player stale');
        expect(wrapper.get('[data-test="health-age"]').text()).toBe('10 minutes ago · stale');
    });

    it('shows Re-link needed over every other state with the re-link action', () => {
        const wrapper = mountPage({
            state: 'no_device',
            link: 'relink_required',
            connected: null,
            receivedAt: longAgo(),
        });

        expect(wrapper.get('[data-test="status-headline"]').text()).toBe('Re-link needed');
        expect(wrapper.get('[data-test="start-badge"]').text()).toBe('Re-link needed');
        expect(wrapper.get('[data-test="relink-button"]').attributes('href')).toBe('/profile/accounts/spotify/link');
        expect(wrapper.get('[data-test="issue-action"]').text()).toBe('Re-link Spotify');
        expect(wrapper.find('[data-test="device-picker"]').exists()).toBe(false);
    });

    it('keeps polling and device details inside a collapsed Diagnostics section', () => {
        const wrapper = mountPage();
        const diagnostics = wrapper.get('[data-test="diagnostics"]');

        expect(diagnostics.element.hasAttribute('open')).toBe(false);
        expect(diagnostics.find('[data-test="health-polling"]').exists()).toBe(true);
        expect(diagnostics.find('[data-test="health-device"]').exists()).toBe(true);
    });

    it('follows the player as it reports over the websocket', async () => {
        const wrapper = mountPage();
        expect(wrapper.get('[data-test="status-headline"]').text()).toBe('Playing');

        const next = {...makeProps().playerState};
        next.status = {...next.status!, isPlaying: false};
        echo.handlers['Party\\UpdatedEvent'](next as never);
        await wrapper.vm.$nextTick();

        expect(wrapper.get('[data-test="status-headline"]').text()).toBe('Paused');
    });
});

describe('quick links', () => {
    it('hides the Web Player link when there is none', () => {
        expect(mountPage({webPlayer: null}).find('[data-test="quick-links"]').text()).not.toContain('Web Player');
        expect(mountPage().find('[data-test="quick-links"]').text()).toContain('Web Player');
    });

    it('copies the join link', async () => {
        const writeText = vi.fn().mockResolvedValue(undefined);
        Object.defineProperty(navigator, 'clipboard', {value: {writeText}, configurable: true});
        const wrapper = mountPage();

        await wrapper.find('[data-test="copy-join"]').trigger('click');
        await flushPromises();

        expect(writeText).toHaveBeenCalledWith('https://mp.test/parties/ABCD');
    });
});

describe('live updates', () => {
    it('reloads control and the queue after a party update', () => {
        vi.useFakeTimers();
        mountPage();

        echo.handlers['Party\\UpdatedEvent'](makeProps().playerState as never);
        expect(inertia.reload).not.toHaveBeenCalled();
        vi.advanceTimersByTime(250);

        expect(inertia.reload).toHaveBeenCalledTimes(1);
        const only = (inertia.reload.mock.calls[0][0] as {only: string[]}).only;
        expect([...only].sort()).toEqual(['attention', 'control', 'upNext', 'upcoming']);
    });

    it('refreshes control and attention periodically, since Encore connecting sends no event', () => {
        vi.useFakeTimers();
        mountPage();

        vi.advanceTimersByTime(15000);

        expect(inertia.reload).toHaveBeenCalledWith({only: ['control', 'attention']});
    });

    it('reloads attention when a flagged request changes', () => {
        vi.useFakeTimers();
        mountPage();

        echo.handlers['FlaggedRequestAdded']();
        vi.advanceTimersByTime(250);

        const only = (inertia.reload.mock.calls[0][0] as {only: string[]}).only;
        expect(only).toContain('attention');
    });
});

describe('upcoming list', () => {
    it('links each row to its song page for scheduling', () => {
        const wrapper = mountPage({state: 'hidden'});

        expect(wrapper.find('[data-test="schedule"]').attributes('href')).toBe('/songs/4');
    });
});
