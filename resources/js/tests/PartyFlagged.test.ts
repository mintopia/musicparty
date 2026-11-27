import {flushPromises, mount} from '@vue/test-utils';
import axios from 'axios';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import Flagged from '@/pages/Parties/Flagged.vue';
import Search from '@/pages/Parties/Search.vue';
import type {FlaggedRequest, LegacyPartyState, TrackAnnotation} from '@/types';

const inertia = vi.hoisted(() => ({get: vi.fn(), reload: vi.fn()}));

vi.mock('@inertiajs/vue3', () => ({
    Head: {render: () => null},
    Deferred: {template: '<div><slot /></div>'},
    Link: {props: ['href'], template: '<a :href="href"><slot /></a>'},
    router: inertia,
    usePage: () => ({props: {flash: {}, auth: {user: null}}}),
}));

vi.mock('@/layouts/AppLayout.vue', () => ({
    default: {template: '<div><slot name="breadcrumbs" /><slot name="header" /><slot /></div>'},
}));

const echo = vi.hoisted(() => ({channels: [] as string[], handlers: {} as Record<string, (payload: never) => void>}));

vi.mock('@/composables/useEcho', () => ({
    useEcho: () => {
        const channel = {
            listen: (event: string, cb: (payload: never) => void) => {
                echo.handlers[event] = cb;
                return channel;
            },
        };

        return {
            onReconnect: () => undefined,
            trackConnection: () => ({value: true}),
            privateChannel: (name: string) => {
                echo.channels.push(name);
                return channel;
            },
            publicChannel: () => channel,
        };
    },
}));

vi.mock('@/routes', () => ({home: () => ({url: '/'})}));

function track(id: string, extra: Partial<TrackAnnotation> = {}): TrackAnnotation {
    return {
        provider_id: 'spotify',
        id,
        name: `Track ${id}`,
        duration_ms: 180000,
        explicit: false,
        external_url: null,
        provider_name: 'Spotify',
        album: {id: 'a', name: 'Album', image_url: null},
        artists: [{id: 'x', name: 'Artist'}],
        queue: {in_queue: false, upcoming_id: null, score: 0, vote: null, requested_by: null},
        eligibility: {allowed: true, outcome: 'flag', reason: null},
        ...extra,
    };
}

function item(id: number, name = 'Sam'): FlaggedRequest {
    return {
        id,
        reason: `Reason ${id}`,
        flagged_at: '2026-01-01T00:00:00Z',
        requester: {id: id + 100, name},
        ai_review_url: null,
        track: track(`t${id}`),
    };
}

const party = {code: 'ABCD', name: 'Party'};
const playerState: LegacyPartyState = {now: null, next: null, current: null, status: null};
const moderationTabs = {flaggedCount: 2, aiReviewEnabled: true};
const originalAdapter = axios.defaults.adapter;
let calls: {method?: string; url?: string; data?: string}[] = [];

function mockAxios(): void {
    axios.defaults.adapter = (config) => {
        calls.push({method: config.method, url: config.url, data: config.data});

        return Promise.resolve({data: {}, status: 200, statusText: 'OK', headers: {}, config} as never);
    };
}

describe('Flagged requests page', () => {
    beforeEach(() => {
        calls = [];
        echo.channels = [];
        echo.handlers = {};
        inertia.reload.mockReset();
        mockAxios();
    });

    afterEach(() => {
        axios.defaults.adapter = originalAdapter;
    });

    it('links the reason to the AI review only when one exists', () => {
        const linked = {...item(1), ai_review_url: '/parties/ABCD/ai-reviews/5'};
        const wrapper = mount(Flagged, {props: {party, playerState, moderationTabs, flagged: [linked, item(2)]}});

        const links = wrapper.findAll('[data-test="ai-review-link"]');
        expect(links).toHaveLength(1);
        expect(links[0].attributes('href')).toBe('/parties/ABCD/ai-reviews/5');
    });

    it('renders each flagged request with reason and requester', () => {
        const wrapper = mount(Flagged, {
            props: {party, playerState, moderationTabs, flagged: [item(1), item(2, 'Alex')]},
        });

        expect(wrapper.findAll('[data-test="flagged-item"]')).toHaveLength(2);
        expect(wrapper.text()).toContain('Requested by Alex');
        expect(wrapper.text()).toContain('Reason 1');
        expect(echo.channels).toEqual(['party.ABCD.managers']);
    });

    it('shows an empty state', () => {
        const wrapper = mount(Flagged, {props: {party, playerState, moderationTabs, flagged: []}});

        expect(wrapper.get('[data-test="empty-state"]').text()).toContain('Nothing waiting for approval');
    });

    it('approves a request and removes it', async () => {
        const wrapper = mount(Flagged, {props: {party, playerState, moderationTabs, flagged: [item(1), item(2)]}});

        await wrapper.get('[data-test="approve"]').trigger('click');
        await flushPromises();

        expect(calls[0]).toMatchObject({method: 'post', url: '/parties/ABCD/flagged/1/approve'});
        expect(wrapper.findAll('[data-test="flagged-item"]')).toHaveLength(1);
    });

    it('rejects with an optional message after confirming', async () => {
        const wrapper = mount(Flagged, {props: {party, playerState, moderationTabs, flagged: [item(1)]}});

        await wrapper.get('[data-test="reject"]').trigger('click');
        await wrapper.get('[data-test="reject-message"]').setValue('Not tonight');
        await wrapper.get('.btn-danger').trigger('click');
        await flushPromises();

        expect(calls).toHaveLength(1);
        expect(calls[0].url).toBe('/parties/ABCD/flagged/1/reject');
        expect(JSON.parse(calls[0].data as string)).toEqual({message: 'Not tonight'});
        expect(wrapper.find('[data-test="flagged-item"]').exists()).toBe(false);
    });

    it('does not reject when the dialog is cancelled', async () => {
        const wrapper = mount(Flagged, {props: {party, playerState, moderationTabs, flagged: [item(1)]}});

        await wrapper.get('[data-test="reject"]').trigger('click');
        await wrapper.get('.link-secondary').trigger('click');

        expect(calls).toHaveLength(0);
        expect(wrapper.find('[data-test="reject-message"]').exists()).toBe(false);
        expect(wrapper.findAll('[data-test="flagged-item"]')).toHaveLength(1);
    });

    it('adds and resolves requests from realtime events', async () => {
        const wrapper = mount(Flagged, {props: {party, playerState, moderationTabs, flagged: [item(1)]}});

        echo.handlers.FlaggedRequestAdded(item(2) as never);
        echo.handlers.FlaggedRequestAdded(item(2) as never);
        await flushPromises();
        expect(wrapper.findAll('[data-test="flagged-item"]')).toHaveLength(2);

        echo.handlers.FlaggedRequestResolved({id: 1} as never);
        await flushPromises();
        expect(wrapper.findAll('[data-test="flagged-item"]')).toHaveLength(1);
        expect(wrapper.text()).toContain('Reason 2');
    });
});

describe('Flagged requests bulk and notices', () => {
    beforeEach(() => {
        calls = [];
        echo.handlers = {};
        inertia.reload.mockReset();
        mockAxios();
    });

    afterEach(() => {
        axios.defaults.adapter = originalAdapter;
    });

    const props = (flagged: FlaggedRequest[], extra = {}) => ({
        party: {...party, canManage: true, flagRequests: true},
        playerState,
        moderationTabs,
        flagged,
        ...extra,
    });

    it('selects rows, shows the bulk bar and bulk approves', async () => {
        const wrapper = mount(Flagged, {props: props([item(1), item(2), item(3)])});
        expect(wrapper.find('[data-test="bulk-bar"]').exists()).toBe(false);

        const boxes = wrapper.findAll('[data-test="select"]');
        await boxes[0].setValue(true);
        await boxes[2].setValue(true);
        expect(wrapper.get('[data-test="bulk-count"]').text()).toBe('2 selected');

        await wrapper.get('[data-test="bulk-approve"]').trigger('click');
        await flushPromises();

        expect(calls[0]).toMatchObject({method: 'post', url: '/parties/ABCD/flagged/approve'});
        expect(JSON.parse(calls[0].data as string)).toEqual({ids: [1, 3]});
        expect(wrapper.findAll('[data-test="flagged-item"]')).toHaveLength(1);
        expect(wrapper.find('[data-test="bulk-bar"]').exists()).toBe(false);
    });

    it('select all then bulk rejects with a message', async () => {
        const wrapper = mount(Flagged, {props: props([item(1), item(2)])});

        await wrapper.get('[data-test="select-all"]').setValue(true);
        await wrapper.get('[data-test="bulk-reject"]').trigger('click');
        await wrapper.get('[data-test="reject-message"]').setValue('Not tonight');
        await wrapper.get('.btn-danger').trigger('click');
        await flushPromises();

        expect(calls[0].url).toBe('/parties/ABCD/flagged/reject');
        expect(JSON.parse(calls[0].data as string)).toEqual({ids: [1, 2], message: 'Not tonight'});
        expect(wrapper.find('[data-test="empty-state"]').exists()).toBe(true);
    });

    it('shows the rule link, or the AI review summary', () => {
        const ai = {
            ...item(2),
            ai_review_url: '/parties/ABCD/ai-reviews/5',
            ai_review: {criterion: 'Vibe', confidence: 0.71, reason: 'Novelty'},
        };
        const rule = {...item(1), rule_url: '/parties/ABCD/moderation'};
        const wrapper = mount(Flagged, {props: props([rule, ai])});

        expect(wrapper.get('[data-test="rule-link"]').attributes('href')).toBe('/parties/ABCD/moderation');
        const text = wrapper.text();
        expect(text).toContain('Rule: Reason 1');
        expect(text).toContain('AI review: Vibe, 71% confident.');
        expect(text).toContain('Novelty');
    });

    it('filters by chip', async () => {
        const ai = {...item(2), ai_review_url: '/x'};
        const wrapper = mount(Flagged, {props: props([item(1), ai])});

        await wrapper.findAll('[data-test="search-chip"]')[1].trigger('click');

        expect(wrapper.findAll('[data-test="flagged-item"]')).toHaveLength(1);
    });

    it('warns when requests are not held and links managers to turn it on', () => {
        const held = mount(Flagged, {props: props([])});
        expect(held.find('[data-test="not-held"]').exists()).toBe(false);

        const notHeld = mount(Flagged, {props: props([], {party: {...party, canManage: true, flagRequests: false}})});
        expect(notHeld.get('[data-test="turn-on"]').attributes('href')).toBe('/parties/ABCD/edit');

        const moderator = mount(Flagged, {
            props: props([], {party: {...party, canManage: false, flagRequests: false}}),
        });
        expect(moderator.find('[data-test="not-held"]').exists()).toBe(true);
        expect(moderator.find('[data-test="turn-on"]').exists()).toBe(false);
    });
});

describe('Search withdraw', () => {
    const idle = playerState;

    beforeEach(() => {
        calls = [];
        inertia.reload.mockReset();
        mockAxios();
    });

    afterEach(() => {
        axios.defaults.adapter = originalAdapter;
    });

    it('withdraws an awaiting request and reloads results', async () => {
        const awaiting = track('t1', {awaiting_approval: true, flagged_request_id: 9});
        const wrapper = mount(Search, {
            props: {
                party: {...party, downvotes: false, canManage: false},
                params: {query: 'q', perPage: 10},
                results: {
                    view: 'overview',
                    top: null,
                    tracks: [awaiting],
                    albums: [],
                    artists: [],
                    empty: false,
                    weak: false,
                },
                playerState: idle,
                moderationTabs,
            },
        });

        expect(wrapper.find('[data-test="awaiting-approval"]').exists()).toBe(true);
        await wrapper.get('[data-test="withdraw"]').trigger('click');
        await flushPromises();

        expect(calls[0]).toMatchObject({method: 'delete', url: '/parties/ABCD/requests/9/flag'});
        expect(inertia.reload).toHaveBeenCalledWith({only: ['results']});
    });
});
