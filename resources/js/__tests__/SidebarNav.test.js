import {beforeEach, describe, expect, it, vi} from 'vitest';
import {mount} from '@vue/test-utils';

const page = vi.hoisted(() => ({props: {}, url: '/'}));
const post = vi.hoisted(() => vi.fn());

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => page,
    router: {post},
    Link: {props: ['href'], template: '<a :href="href"><slot /></a>'},
}));

import SidebarNav from '../Components/SidebarNav.vue';

beforeEach(() => {
    post.mockReset();
    page.props = {appName: 'Music Party', auth: {user: {is_admin: false}}, parties: []};
});

describe('SidebarNav', () => {
    it('logs out with a POST to /logout', async () => {
        const wrapper = mount(SidebarNav);

        await wrapper.find('[data-testid="logout"]').trigger('click');

        expect(post).toHaveBeenCalledWith('/logout');
    });
});

describe('SidebarNav admin group', () => {
    it('lists children and shared admin links, collapsible', async () => {
        page.url = '/admin/users';
        page.props = {
            appName: 'MP',
            auth: {user: {is_admin: true}},
            parties: [],
            adminLinks: [{label: 'Pulse', href: '/pulse'}, {label: 'Horizon', href: '/horizon'}],
        };
        const wrapper = mount(SidebarNav);
        const labels = wrapper.findAll('a').map((a) => a.text());

        expect(labels).toEqual(expect.arrayContaining(['Dashboard', 'Users', 'Parties', 'Settings', 'Tokens', 'Pulse', 'Horizon']));
        expect(labels).not.toContain('Telescope');
        expect(wrapper.find('a[href="/pulse"]').exists()).toBe(true);

        await wrapper.find('[data-testid="admin-group"]').trigger('click');
        expect(wrapper.find('a[href="/admin/users"]').exists()).toBe(false);
    });

    it('starts collapsed outside admin pages', () => {
        page.url = '/';
        page.props = {appName: 'MP', auth: {user: {is_admin: true}}, parties: []};
        expect(mount(SidebarNav).find('a[href="/admin/users"]').exists()).toBe(false);
    });

    it('marks settings active on theme pages', () => {
        page.url = '/admin/theme';
        page.props = {appName: 'MP', auth: {user: {is_admin: true}}, parties: []};
        expect(mount(SidebarNav).find('a[href="/admin/settings"]').attributes('aria-current')).toBe('page');
    });
});
