import {mount} from '@vue/test-utils';
import {describe, expect, it, vi} from 'vitest';
import Album from '@/pages/Parties/Album.vue';
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

vi.mock('@/routes/parties/artists', () => ({
    show: ({party, artist}: {party: string; artist: string}) => ({url: `/parties/${party}/artists/${artist}`}),
}));

const idle: LegacyPartyState = {now: null, next: null, current: null, status: null};

describe('Party album page', () => {
    it('links each header artist to its artist page, preserving the search query', () => {
        const wrapper = mount(Album, {
            props: {
                party: {code: 'ABCD', name: 'Party', downvotes: false, canManage: false},
                query: 'abba',
                album: {
                    id: 'alb',
                    name: 'Arrival',
                    image_url: null,
                    artists: [
                        {id: 'a1', name: 'ABBA'},
                        {id: 'a2', name: 'Guest'},
                    ],
                    release_year: '1976',
                    track_count: 10,
                },
                tracks: null,
                retry: false,
                playerState: idle,
            },
        });

        const links = wrapper.get('[data-test="album-artists"]').findAll('a');

        expect(links.map((link) => link.attributes('href'))).toEqual([
            '/parties/ABCD/artists/a1?query=abba',
            '/parties/ABCD/artists/a2?query=abba',
        ]);
        expect(links.map((link) => link.text())).toEqual(['ABBA', 'Guest']);
    });

    it('runs breadcrumbs from the Party to the last search to the album', () => {
        const wrapper = mount(Album, {
            props: {
                party: {code: 'ABCD', name: 'Party', downvotes: false, canManage: false},
                query: 'abba',
                album: {id: 'alb', name: 'Arrival', image_url: null, artists: [], release_year: null, track_count: 1},
                tracks: null,
                retry: false,
                playerState: idle,
            },
        });

        const items = wrapper.findAll('.breadcrumb-item');

        expect(items.map((item) => item.text())).toEqual(['Home', 'Party', 'Search', 'Arrival']);
        expect(items[1].get('a').attributes('href')).toBe('/parties/ABCD');
        expect(items[2].get('a').attributes('href')).toBe('/parties/ABCD/search?query=abba');
    });

    it('offers a way back to the search when the album is missing', () => {
        const wrapper = mount(Album, {
            props: {
                party: {code: 'ABCD', name: 'Party', downvotes: false, canManage: false},
                query: 'abba',
                album: null,
                tracks: null,
                retry: false,
                playerState: idle,
            },
        });

        expect(wrapper.findAll('.breadcrumb-item').at(-1)?.text()).toBe('Album');
        expect(wrapper.get('a.btn').attributes('href')).toBe('/parties/ABCD/search?query=abba');
    });
});
