import {mount} from '@vue/test-utils';
import {describe, expect, it, vi} from 'vitest';
import Artist from '@/pages/Parties/Artist.vue';
import type {LegacyPartyState} from '@/types';

vi.mock('@inertiajs/vue3', () => ({
    Head: {render: () => null},
    Link: {props: ['href'], template: '<a :href="href"><slot /></a>'},
    router: {get: vi.fn(), reload: vi.fn()},
    usePage: () => ({props: {flash: {}, auth: {user: null}}}),
}));

vi.mock('@/layouts/AppLayout.vue', () => ({
    default: {template: '<div><slot name="breadcrumbs" /><slot name="header" /><slot /></div>'},
}));

vi.mock('@/composables/useEcho', () => ({
    useEcho: () => ({
        privateChannel: () => ({listen: () => undefined}),
        publicChannel: () => ({listen: () => undefined}),
    }),
}));

vi.mock('@/routes', () => ({home: () => ({url: '/'})}));

const idle: LegacyPartyState = {now: null, next: null, current: null, status: null};
const party = {code: 'ABCD', name: 'Party', downvotes: false, canManage: false};

describe('Party artist page', () => {
    it('runs breadcrumbs from the Party to the last search to the artist', () => {
        const wrapper = mount(Artist, {
            props: {
                party,
                query: 'abba',
                artist: {id: 'a1', name: 'ABBA', image_url: null},
                tracks: null,
                albums: null,
                retry: false,
                playerState: idle,
            },
        });

        const items = wrapper.findAll('.breadcrumb-item');

        expect(items.map((item) => item.text())).toEqual(['Home', 'Party', 'Search', 'ABBA']);
        expect(items[2].get('a').attributes('href')).toBe('/parties/ABCD/search?query=abba');
    });

    it('preserves the query on album card links', () => {
        const wrapper = mount(Artist, {
            props: {
                party,
                query: 'ab ba',
                artist: {id: 'a1', name: 'ABBA', image_url: null},
                tracks: null,
                albums: {
                    data: [{id: 'alb1', name: 'Arrival', image_url: null}],
                    current_page: 1,
                    last_page: 1,
                    prev_page_url: null,
                    next_page_url: null,
                    links: [],
                },
                retry: false,
                playerState: idle,
            },
        });

        expect(wrapper.get('[data-test="artist-albums"] a').attributes('href')).toBe(
            '/parties/ABCD/albums/alb1?query=ab%20ba',
        );
    });

    it('links back to the plain search when there is no query and the artist is missing', () => {
        const wrapper = mount(Artist, {
            props: {party, query: null, artist: null, tracks: null, albums: null, retry: false, playerState: idle},
        });

        expect(wrapper.findAll('.breadcrumb-item').at(-1)?.text()).toBe('Artist');
        expect(wrapper.get('a.btn').attributes('href')).toBe('/parties/ABCD/search');
    });
});
