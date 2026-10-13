import {flushPromises, mount} from '@vue/test-utils';
import axios from 'axios';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import Search from '@/pages/Parties/Search.vue';
import type {Paginator, TrackAnnotation} from '@/types';

const inertia = vi.hoisted(() => ({get: vi.fn(), reload: vi.fn()}));

vi.mock('@inertiajs/vue3', () => ({
    Head: {render: () => null},
    Link: {props: ['href'], template: '<a :href="href"><slot /></a>'},
    router: inertia,
    usePage: () => ({props: {flash: {}, auth: {user: null}}}),
}));

vi.mock('@/layouts/AppLayout.vue', () => ({
    default: {template: '<div><slot name="breadcrumbs" /><slot /></div>'},
}));

vi.mock('@/routes', () => ({home: () => ({url: '/'})}));

function track(id: string, queue: Partial<TrackAnnotation['queue']> = {}, allowed = true): TrackAnnotation {
    return {
        provider_id: 'spotify',
        id,
        name: `Track ${id}`,
        duration_ms: 180000,
        explicit: false,
        album: {id: 'a', name: 'Album', image_url: null},
        artists: [{id: 'x', name: 'Artist'}],
        queue: {in_queue: false, upcoming_id: null, score: 0, vote: null, requested_by: null, ...queue},
        eligibility: {allowed, reason: allowed ? null : 'Not allowed'},
    };
}

function results(data: TrackAnnotation[]): Paginator<TrackAnnotation> {
    return {data, current_page: 1, last_page: 1, prev_page_url: null, next_page_url: null, links: []};
}

const party = {code: 'ABCD', name: 'Party', downvotes: false, canManage: false};
const originalAdapter = axios.defaults.adapter;

describe('Party search page', () => {
    beforeEach(() => {
        inertia.get.mockReset();
        inertia.reload.mockReset();
    });

    afterEach(() => {
        axios.defaults.adapter = originalAdapter;
    });

    it('shows no results section until a search has run', () => {
        const wrapper = mount(Search, {props: {party, params: {query: null, perPage: 20}, results: null}});

        expect(wrapper.text()).not.toContain('Results');
    });

    it('submits the query through Inertia', async () => {
        const wrapper = mount(Search, {props: {party, params: {query: null, perPage: 20}, results: null}});

        await wrapper.get('#query').setValue('abba');
        await wrapper.get('form').trigger('submit');

        expect(inertia.get).toHaveBeenCalledWith('/parties/ABCD/search', {query: 'abba', perPage: undefined});
    });

    it('requests a track and reloads the results', async () => {
        axios.defaults.adapter = () => Promise.resolve({data: {}, status: 201, statusText: 'Created', headers: {}, config: {}} as never);
        const wrapper = mount(Search, {props: {party, params: {query: 'q', perPage: 20}, results: results([track('t1')])}});

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
        const wrapper = mount(Search, {props: {party, params: {query: 'q', perPage: 20}, results: results([track('t1')])}});

        await wrapper.get('[data-test="request"]').trigger('click');
        await flushPromises();

        expect(wrapper.get('[data-test="message"]').text()).toBe('Already requested');
        expect(inertia.reload).not.toHaveBeenCalled();
    });

    it('updates the score after voting on a queued track', async () => {
        axios.defaults.adapter = () =>
            Promise.resolve({data: {data: {score: 4, vote: 1, user: 'Sam'}}, status: 200, statusText: 'OK', headers: {}, config: {}} as never);
        const queued = track('t1', {in_queue: true, upcoming_id: 9, score: 3, requested_by: 'Sam'});
        const wrapper = mount(Search, {props: {party, params: {query: 'q', perPage: 20}, results: results([queued])}});

        await wrapper.get('[data-test="heart"]').trigger('click');
        await flushPromises();

        expect(wrapper.get('[data-test="score"]').text()).toBe('4');
    });
});
