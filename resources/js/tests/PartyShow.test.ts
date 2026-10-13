import {flushPromises, mount} from '@vue/test-utils';
import axios from 'axios';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import Upcoming from '@/components/Upcoming.vue';
import Show from '@/pages/Parties/Show.vue';
import type {LegacyPartyState} from '@/types';

const inertia = vi.hoisted(() => ({get: vi.fn(), reload: vi.fn()}));
const echo = vi.hoisted(() => ({
    handlers: {} as Record<string, (payload: unknown) => void>,
    reconnects: [] as Array<() => void>,
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: {render: () => null},
    Link: {props: ['href'], template: '<a :href="href"><slot /></a>'},
    router: inertia,
}));
vi.mock('@/layouts/AppLayout.vue', () => ({
    default: {template: '<div><slot name="breadcrumbs" /><slot name="header" /><slot /></div>'},
}));
vi.mock('@/routes', () => ({home: () => ({url: '/'})}));
vi.mock('@ts-pro/vue-eternal-loading', () => ({VueEternalLoading: {template: '<div />'}}));
vi.mock('@/composables/useEcho', () => {
    const listen = (event: string, cb: (payload: unknown) => void) => {
        echo.handlers[event] = cb;
    };
    return {
        useEcho: () => ({
            onReconnect: (cb: () => void) => echo.reconnects.push(cb),
            publicChannel: () => ({listen}),
            privateChannel: () => ({listen}),
        }),
    };
});

const song = (id: number, extra: Record<string, unknown> = {}) => ({
    id,
    name: `Song ${id}`,
    artists: [{name: 'A'}],
    album: {name: 'Al', image_url: ''},
    length: 1000,
    score: 0,
    vote: 0,
    user: 'u',
    css_classes: '',
    fallback_name: null,
    created_at: '2030-01-01T00:00:00Z',
    ...extra,
});

const idle: LegacyPartyState = {now: null, next: null, current: null, status: null};
const party = {code: 'ABCD', name: 'Party', downvotes: false, canManage: true, weighted: false};
const props = {
    party,
    playerState: idle,
    upcoming: [song(1), song(2)],
    timezone: 'UTC',
    spotifyNeedsRelink: false,
    relinkUrl: '/linked/spotify',
    searchUrl: '/parties/ABCD/search',
};

const response = (data: unknown) => ({data, status: 200, statusText: 'OK', headers: {}, config: {}}) as never;
const originalAdapter = axios.defaults.adapter;

describe('Party show page', () => {
    beforeEach(() => {
        inertia.get.mockReset();
        echo.reconnects.length = 0;
    });
    afterEach(() => {
        axios.defaults.adapter = originalAdapter;
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    it('renders the queue, up next and search, with a Queue heading for unweighted parties', () => {
        const wrapper = mount(Show, {
            props: {...props, playerState: {...idle, next: {...song(9), name: 'Coming Up'} as never}},
        });

        expect(wrapper.text()).toContain('Up Next');
        expect(wrapper.text()).toContain('Coming Up');
        expect(wrapper.get('h3.mt-2').text()).toBe('Queue');
        expect(wrapper.findAll('.list-group-item')).toHaveLength(3);
        expect(wrapper.text()).not.toContain('more likely to be played');
    });

    it('labels weighted parties as upcoming songs', () => {
        const wrapper = mount(Show, {props: {...props, party: {...party, weighted: true}}});

        expect(wrapper.get('h3.mt-2').text()).toBe('Upcoming Songs');
        expect(wrapper.text()).toContain('Tracks with more upvotes are more likely to be played.');
    });

    it('shows the spotify relink banner only when needed', () => {
        expect(mount(Show, {props}).find('#spotify-relink-banner').exists()).toBe(false);

        const banner = mount(Show, {props: {...props, spotifyNeedsRelink: true}}).get('#spotify-relink-banner');

        expect(banner.get('a').attributes('href')).toBe('/linked/spotify');
    });

    it('submits the search form through Inertia', async () => {
        const wrapper = mount(Show, {props});

        await wrapper.get('#query').setValue('abba');
        await wrapper.get('form').trigger('submit');

        expect(inertia.get).toHaveBeenCalledWith('/parties/ABCD/search', {query: 'abba'});
    });

    it('pings every 60 seconds and stops after unmount', async () => {
        vi.useFakeTimers();
        const fetchMock = vi.fn().mockResolvedValue({});
        vi.stubGlobal('fetch', fetchMock);
        const wrapper = mount(Show, {props});

        await vi.advanceTimersByTimeAsync(120000);
        expect(fetchMock).toHaveBeenCalledTimes(2);
        expect(fetchMock).toHaveBeenCalledWith('/api/v1/ping');

        wrapper.unmount();
        await vi.advanceTimersByTimeAsync(120000);

        expect(fetchMock).toHaveBeenCalledTimes(2);
    });

    it('has no YouTube entry point', () => {
        expect(mount(Show, {props}).html().toLowerCase()).not.toContain('youtube');
    });
});

describe('Upcoming queue', () => {
    beforeEach(() => {
        echo.reconnects.length = 0;
    });
    afterEach(() => {
        axios.defaults.adapter = originalAdapter;
    });

    const mountQueue = (extra: Record<string, unknown> = {}) =>
        mount(Upcoming, {props: {party: 'ABCD', initialState: [song(1), song(2)], ...extra}});

    it('updates the row from the vote response and re-sorts by score', async () => {
        axios.defaults.adapter = () => Promise.resolve(response({data: song(2, {score: 5, vote: 1})}));
        const wrapper = mountQueue();

        await wrapper.findAll('.ti-heart')[1].trigger('click');
        await flushPromises();

        expect(wrapper.findAll('.list-group-item')[0].text()).toContain('Song 2');
        expect(wrapper.find('.ti-heart-filled').exists()).toBe(true);
    });

    it('shows the server message when a vote is refused', async () => {
        axios.defaults.adapter = (config) =>
            Promise.reject(
                Object.assign(new Error('refused'), {
                    isAxiosError: true,
                    config,
                    response: {status: 400, data: {message: 'No voting for your own track'}},
                }),
            );
        const wrapper = mountQueue();

        await wrapper.get('.ti-heart').trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('No voting for your own track');
    });

    it('removes a row and calls the delete route for managers', async () => {
        const requests: string[] = [];
        axios.defaults.adapter = (config) => {
            requests.push(`${config.method} ${config.url}`);
            return Promise.resolve(response({}));
        };
        const wrapper = mountQueue({canManage: true});

        await wrapper.get('.ti-x').trigger('click');

        expect(requests).toEqual(['delete /api/v1/parties/ABCD/upcomingsongs/1']);
        expect(wrapper.text()).not.toContain('Song 1');
    });

    it('applies pushed updates and removals', async () => {
        const wrapper = mountQueue();

        echo.handlers['UpcomingSong\\UpdatedEvent'](song(3, {score: 9}));
        echo.handlers['UpcomingSong\\RemovedEvent']({id: 1});
        await wrapper.vm.$nextTick();

        const rows = wrapper.findAll('.list-group-item');
        expect(rows).toHaveLength(2);
        expect(rows[0].text()).toContain('Song 3');
    });

    it('refetches the queue after an echo reconnect', async () => {
        axios.defaults.adapter = () => Promise.resolve(response({data: [song(7)]}));
        const wrapper = mountQueue();

        echo.reconnects[0]();
        await flushPromises();

        expect(wrapper.text()).toContain('Song 7');
        expect(wrapper.text()).not.toContain('Song 1');
    });
});
