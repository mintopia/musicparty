import {flushPromises, mount} from '@vue/test-utils';
import axios from 'axios';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import Flagged from '@/pages/Parties/Flagged.vue';
import Search from '@/pages/Parties/Search.vue';
import type {FlaggedRequest, LegacyPartyState, TrackAnnotation} from '@/types';

const inertia = vi.hoisted(() => ({get: vi.fn(), reload: vi.fn()}));

vi.mock('@inertiajs/vue3', () => ({
    Head: {render: () => null},
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
        track: track(`t${id}`),
    };
}

const party = {code: 'ABCD', name: 'Party'};
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

    it('renders each flagged request with reason and requester', () => {
        const wrapper = mount(Flagged, {props: {party, flagged: [item(1), item(2, 'Alex')]}});

        expect(wrapper.findAll('[data-test="flagged-item"]')).toHaveLength(2);
        expect(wrapper.text()).toContain('Requested by Alex');
        expect(wrapper.text()).toContain('Reason 1');
        expect(echo.channels).toEqual(['party.ABCD.managers']);
    });

    it('shows an empty state', () => {
        const wrapper = mount(Flagged, {props: {party, flagged: []}});

        expect(wrapper.get('[data-test="empty-state"]').text()).toContain('No flagged requests');
    });

    it('approves a request and removes it', async () => {
        const wrapper = mount(Flagged, {props: {party, flagged: [item(1), item(2)]}});

        await wrapper.get('[data-test="approve"]').trigger('click');
        await flushPromises();

        expect(calls[0]).toMatchObject({method: 'post', url: '/parties/ABCD/flagged/1/approve'});
        expect(wrapper.findAll('[data-test="flagged-item"]')).toHaveLength(1);
    });

    it('rejects with an optional message after confirming', async () => {
        const wrapper = mount(Flagged, {props: {party, flagged: [item(1)]}});

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
        const wrapper = mount(Flagged, {props: {party, flagged: [item(1)]}});

        await wrapper.get('[data-test="reject"]').trigger('click');
        await wrapper.get('.link-secondary').trigger('click');

        expect(calls).toHaveLength(0);
        expect(wrapper.find('[data-test="reject-message"]').exists()).toBe(false);
        expect(wrapper.findAll('[data-test="flagged-item"]')).toHaveLength(1);
    });

    it('adds and resolves requests from realtime events', async () => {
        const wrapper = mount(Flagged, {props: {party, flagged: [item(1)]}});

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

describe('Search withdraw', () => {
    const idle: LegacyPartyState = {now: null, next: null, current: null, status: null};

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
                params: {query: 'q', perPage: 20},
                results: {data: [awaiting], current_page: 1, last_page: 1, prev_page_url: null, next_page_url: null, links: []},
                playerState: idle,
            },
        });

        expect(wrapper.find('[data-test="awaiting-approval"]').exists()).toBe(true);
        await wrapper.get('[data-test="withdraw"]').trigger('click');
        await flushPromises();

        expect(calls[0]).toMatchObject({method: 'delete', url: '/parties/ABCD/requests/9/flag'});
        expect(inertia.reload).toHaveBeenCalledWith({only: ['results']});
    });
});
