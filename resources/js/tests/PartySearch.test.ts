import {flushPromises, mount} from '@vue/test-utils';
import {defineComponent, h} from 'vue';
import axios from 'axios';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import Search from '@/pages/Parties/Search.vue';
import type {LegacyPartyState, SearchOverviewResults, TrackAnnotation} from '@/types';

const inertia = vi.hoisted(() => ({get: vi.fn(), reload: vi.fn()}));
const deferred = vi.hoisted(() => ({mode: 'loaded' as 'loaded' | 'loading' | 'rescued'}));

vi.mock('@inertiajs/vue3', () => ({
    Head: {render: () => null},
    Deferred: defineComponent({
        props: {data: {type: String, required: true}},
        setup:
            (_props, {slots}) =>
            () =>
                deferred.mode === 'loading'
                    ? h('div', slots.fallback?.())
                    : deferred.mode === 'rescued'
                      ? h('div', slots.rescue?.())
                      : h('div', slots.default?.({reloading: false})),
    }),
    Link: {props: ['href'], template: '<a :href="href"><slot /></a>'},
    router: inertia,
    usePage: () => ({props: {flash: {}, auth: {user: null}}}),
}));

vi.mock('@/layouts/AppLayout.vue', () => ({
    default: {template: '<div><slot name="breadcrumbs" /><slot name="header" /><slot /></div>'},
}));

const listeners = vi.hoisted(() => ({handlers: {} as Record<string, (payload: unknown) => void>}));

vi.mock('@/composables/useEcho', () => ({
    useEcho: () => ({
        onReconnect: () => undefined,
        trackConnection: () => ({value: true}),
        privateChannel: () => ({
            listen: (event: string, cb: (payload: unknown) => void) => {
                listeners.handlers[event] = cb;
            },
        }),
        publicChannel: () => ({
            listen: (event: string, cb: (payload: unknown) => void) => {
                listeners.handlers[event] = cb;
            },
        }),
    }),
}));

vi.mock('@/routes', () => ({home: () => ({url: '/'})}));

function track(id: string, queue: Partial<TrackAnnotation['queue']> = {}, allowed = true): TrackAnnotation {
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
        queue: {in_queue: false, upcoming_id: null, score: 0, vote: null, requested_by: null, ...queue},
        eligibility: {allowed, outcome: allowed ? 'allow' : 'reject', reason: allowed ? null : 'Not allowed'},
    };
}

function results(tracks: TrackAnnotation[]): SearchOverviewResults {
    return {view: 'overview', top: null, tracks, albums: [], artists: [], empty: false, weak: false};
}

const idle: LegacyPartyState = {now: null, next: null, current: null, status: null};
const party = {code: 'ABCD', name: 'Party', downvotes: false, canManage: false};
const originalAdapter = axios.defaults.adapter;

describe('Party search page', () => {
    beforeEach(() => {
        inertia.get.mockReset();
        inertia.reload.mockReset();
        deferred.mode = 'loaded';
    });

    afterEach(() => {
        axios.defaults.adapter = originalAdapter;
    });

    it('shows no results section until a search has run', () => {
        const wrapper = mount(Search, {
            props: {party, params: {query: null, perPage: 10}, results: null, playerState: idle},
        });

        expect(wrapper.text()).not.toContain('Results');
    });

    it('renders the party player above the search and follows live updates', async () => {
        const wrapper = mount(Search, {
            props: {party, params: {query: null, perPage: 10}, results: null, playerState: idle},
        });

        expect(wrapper.find('[data-test="nothing-playing"]').exists()).toBe(true);

        listeners.handlers['Party\\UpdatedEvent']({
            ...idle,
            now: {name: 'Live Song', artists: [{name: 'Band'}], album: {name: 'LP', image_url: ''}},
        });
        await flushPromises();

        expect(wrapper.get('[data-test="now-name"]').text()).toBe('Live Song');
    });

    it('submits the query through Inertia', async () => {
        const wrapper = mount(Search, {
            props: {party, params: {query: null, perPage: 10}, results: null, playerState: idle},
        });

        await wrapper.get('#query').setValue('abba');
        await wrapper.get('form').trigger('submit');

        expect(inertia.get).toHaveBeenCalledWith(
            '/parties/ABCD/search',
            {query: 'abba', perPage: undefined},
            expect.objectContaining({onStart: expect.any(Function)}),
        );
    });

    it('shows track placeholders while the deferred results load', () => {
        deferred.mode = 'loading';
        const wrapper = mount(Search, {
            props: {party, params: {query: 'q', perPage: 10}, playerState: idle},
        });

        expect(wrapper.find('[data-test="results-loading"]').exists()).toBe(true);
        expect(wrapper.findAll('[data-test="track-placeholder"]').length).toBeGreaterThan(0);
        expect(wrapper.find('[data-test="track-row"]').exists()).toBe(false);
    });

    it('fills in the results once they arrive', () => {
        const wrapper = mount(Search, {
            props: {party, params: {query: 'q', perPage: 10}, results: results([track('t1')]), playerState: idle},
        });

        expect(wrapper.find('[data-test="track-placeholder"]').exists()).toBe(false);
        expect(wrapper.findAll('[data-test="track-row"]')).toHaveLength(1);
    });

    it('shows a friendly error with Retry when the search reports a failure', async () => {
        const wrapper = mount(Search, {
            props: {
                party,
                params: {query: 'q', perPage: 10},
                results: {
                    error: {code: 'timeout', message: "Spotify didn't answer in time.", retryable: true},
                },
                playerState: idle,
            },
        });

        expect(wrapper.get('[data-test="results-error"]').text()).toContain("didn't answer in time");

        await wrapper.get('[data-test="retry"]').trigger('click');

        expect(inertia.reload).toHaveBeenCalledWith(expect.objectContaining({only: ['results']}));
    });

    it('offers no Retry for a failure that cannot be retried', () => {
        const wrapper = mount(Search, {
            props: {
                party,
                params: {query: 'q', perPage: 10},
                results: {error: {code: 'relink', message: 'Needs re-linking', retryable: false}},
                playerState: idle,
            },
        });

        expect(wrapper.find('[data-test="retry"]').exists()).toBe(false);
    });

    it('shows the error with Retry when the deferred load itself fails', () => {
        deferred.mode = 'rescued';
        const wrapper = mount(Search, {
            props: {party, params: {query: 'q', perPage: 10}, playerState: idle},
        });

        expect(wrapper.find('[data-test="results-error"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="retry"]').exists()).toBe(true);
    });

    it('shows placeholders again as soon as a new search starts', async () => {
        const wrapper = mount(Search, {
            props: {party, params: {query: 'q', perPage: 10}, results: results([track('t1')]), playerState: idle},
        });

        await wrapper.get('#query').setValue('abba');
        await wrapper.get('form').trigger('submit');
        const options = inertia.get.mock.calls[0][2] as {onStart: () => void; onFinish: () => void};
        options.onStart();
        await flushPromises();

        expect(wrapper.find('[data-test="results-loading"]').exists()).toBe(true);

        options.onFinish();
        await flushPromises();

        expect(wrapper.find('[data-test="results-loading"]').exists()).toBe(false);
    });

    it('requests a track and reloads the results', async () => {
        axios.defaults.adapter = () =>
            Promise.resolve({data: {}, status: 201, statusText: 'Created', headers: {}, config: {}} as never);
        const wrapper = mount(Search, {
            props: {party, params: {query: 'q', perPage: 10}, results: results([track('t1')]), playerState: idle},
        });

        await wrapper.get('[data-test="request"]').trigger('click');
        await flushPromises();

        expect(inertia.reload).toHaveBeenCalledWith({only: ['results']});
    });

    it('shows the blocked reason when a request is rejected', async () => {
        axios.defaults.adapter = (config) =>
            Promise.reject(
                Object.assign(new Error('blocked'), {
                    isAxiosError: true,
                    config,
                    response: {status: 400, data: {message: 'Already requested'}},
                }),
            );
        const wrapper = mount(Search, {
            props: {party, params: {query: 'q', perPage: 10}, results: results([track('t1')]), playerState: idle},
        });

        await wrapper.get('[data-test="request"]').trigger('click');
        await flushPromises();

        expect(wrapper.get('[data-test="message"]').text()).toBe('Already requested');
        expect(inertia.reload).not.toHaveBeenCalled();
    });

    it('updates the score after voting on a queued track', async () => {
        axios.defaults.adapter = () =>
            Promise.resolve({
                data: {data: {score: 4, vote: 1, user: 'Sam'}},
                status: 200,
                statusText: 'OK',
                headers: {},
                config: {},
            } as never);
        const queued = track('t1', {in_queue: true, upcoming_id: 9, score: 3, requested_by: 'Sam'});
        const wrapper = mount(Search, {
            props: {party, params: {query: 'q', perPage: 10}, results: results([queued]), playerState: idle},
        });

        await wrapper.get('[data-test="heart"]').trigger('click');
        await flushPromises();

        expect(wrapper.get('[data-test="score"]').text()).toBe('4');
    });

    it('links track albums and artists to their pages, preserving the query', () => {
        const wrapper = mount(Search, {
            props: {party, params: {query: 'ab ba', perPage: 10}, results: results([track('t1')]), playerState: idle},
        });

        const hrefs = wrapper.findAll('a').map((link) => link.attributes('href'));

        expect(hrefs).toContain('/parties/ABCD/artists/x?query=ab%20ba');
        expect(hrefs).toContain('/parties/ABCD/albums/a?query=ab%20ba');
    });

    it('links without a query string when nothing was searched', () => {
        const wrapper = mount(Search, {
            props: {party, params: {query: null, perPage: 10}, results: results([track('t1')]), playerState: idle},
        });

        const hrefs = wrapper.findAll('a').map((link) => link.attributes('href'));

        expect(hrefs).toContain('/parties/ABCD/artists/x');
        expect(hrefs).toContain('/parties/ABCD/albums/a');
    });

    it('patches queue badges from broadcasts without reloading the search', async () => {
        const wrapper = mount(Search, {
            props: {
                party,
                params: {query: 'abba', perPage: 10},
                results: results([track('t1'), track('t2', {in_queue: true, upcoming_id: 9, score: 2})]),
                playerState: idle,
            },
        });

        listeners.handlers['UpcomingSong\\UpdatedEvent']({
            id: 5,
            provider_track_id: 't1',
            score: 3,
            user: 'sam',
            user_id: 7,
        });
        await flushPromises();
        expect(wrapper.text()).toContain('Requested by sam');

        listeners.handlers['UpcomingSong\\RemovedEvent']({id: 9});
        await flushPromises();

        expect(inertia.reload).not.toHaveBeenCalled();
        expect(inertia.get).not.toHaveBeenCalled();
    });
});
