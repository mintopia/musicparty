import {beforeEach, describe, expect, it, vi} from 'vitest';
import {mount} from '@vue/test-utils';

const page = vi.hoisted(() => ({props: {}, url: '/'}));

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => page,
    router: {post: vi.fn()},
    Link: {props: ['href'], template: '<a :href="href"><slot /></a>'},
}));

import SidebarNav from '../Components/SidebarNav.vue';
import MobileTabBar from '../Components/MobileTabBar.vue';

const menu = [
    {key: 'queue', label: 'Queue', href: '/parties/ABCD'},
    {key: 'tv', label: 'TV Mode', href: '/parties/ABCD/tv'},
    {key: 'pending', label: 'Pending', href: '/parties/ABCD/requests/pending'},
    {key: 'theme', label: 'Theme', href: '/parties/ABCD/theme'},
];

beforeEach(() => {
    page.url = '/parties/ABCD';
    page.props = {
        appName: 'Music Party',
        auth: {user: {is_admin: false}},
        parties: [{code: 'ABCD', name: 'Friday LAN'}],
        partyContext: {party: {code: 'ABCD'}, menu},
    };
});

describe('party menu', () => {
    it('renders every shared menu entry in the sidebar', () => {
        const links = mount(SidebarNav).findAll('a').map((a) => a.text());

        expect(links).toEqual(expect.arrayContaining(['Queue', 'TV Mode', 'Pending', 'Theme']));
    });

    it('links TV Mode to the party TV page', () => {
        const tv = mount(SidebarNav).findAll('a').find((a) => a.text() === 'TV Mode');

        expect(tv.attributes('href')).toBe('/parties/ABCD/tv');
    });

    it('marks the active entry', () => {
        const queue = mount(SidebarNav).findAll('a').find((a) => a.text() === 'Queue');

        expect(queue.attributes('aria-current')).toBe('page');
    });

    it('links TV Mode from the mobile tab bar', () => {
        const tv = mount(MobileTabBar, {props: {code: 'ABCD'}}).findAll('a').find((a) => a.text() === 'TV Mode');

        expect(tv.attributes('href')).toBe('/parties/ABCD/tv');
    });

    it('falls back to the member entries without a shared menu', () => {
        page.props.partyContext = null;

        expect(mount(SidebarNav).findAll('a').map((a) => a.text())).toContain('TV Mode');
    });
});
