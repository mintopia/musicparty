import {beforeEach, describe, expect, it, vi} from 'vitest';
import {mount} from '@vue/test-utils';
import {h} from 'vue';

const page = vi.hoisted(() => ({props: {}, url: '/'}));
const router = vi.hoisted(() => ({get: vi.fn(), post: vi.fn(), delete: vi.fn()}));

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => page,
    router,
    useForm: (data) => ({...data, post: vi.fn(), reset: vi.fn()}),
    Head: {render: () => null},
    Link: {props: ['href'], render() { return h('a', {href: this.href}, this.$slots.default?.()); }},
}));

import Dashboard from '../pages/Admin/Dashboard.vue';
import TokensIndex from '../pages/Admin/Tokens/Index.vue';
import PartiesIndex from '../pages/Admin/Parties/Index.vue';
import SidebarNav from '../Components/SidebarNav.vue';

beforeEach(() => {
    vi.useFakeTimers();
    router.get.mockReset();
    router.post.mockReset();
    router.delete.mockReset();
    page.props = {appName: 'MP', auth: {user: {id: 1, is_admin: true}}, parties: [], errors: {}};
    page.url = '/admin';
});

describe('SidebarNav admin group', () => {
    it('shows the group with children for admins', () => {
        const wrapper = mount(SidebarNav);
        expect(wrapper.find('[data-testid="admin-group"]').exists()).toBe(true);
        expect(wrapper.find('a[href="/admin"]').text()).toBe('Dashboard');
    });

    it('is hidden for non-admins and guests', () => {
        page.props.auth.user.is_admin = false;
        expect(mount(SidebarNav).find('[data-testid="admin-group"]').exists()).toBe(false);
        page.props.auth.user = null;
        expect(mount(SidebarNav).find('[data-testid="admin-group"]').exists()).toBe(false);
    });
});

describe('Admin Dashboard', () => {
    const stats = {users: {total: 59, lastWeek: 3}, parties: {total: 4, live: 1}};

    it('shows user and party stat cards linking to their lists', () => {
        const wrapper = mount(Dashboard, {props: {stats}});

        expect(wrapper.get('[data-testid="card-users"]').text()).toContain('59 Users');
        expect(wrapper.get('[data-testid="card-users"]').text()).toContain('3 in the last week');
        expect(wrapper.get('[data-testid="card-users"]').attributes('href')).toBe('/admin/users');
        expect(wrapper.get('[data-testid="card-parties"]').text()).toContain('4 Parties');
        expect(wrapper.get('[data-testid="card-parties"]').text()).toContain('1 live');
        expect(wrapper.find('[data-testid="card-telescope"]').exists()).toBe(false);
    });
});

describe('Admin Parties', () => {
    const parties = [
        {id: 5, name: 'LAN One', acting_as_host: false},
        {id: 6, name: 'LAN Two', acting_as_host: true},
    ];

    it('shows the badge only for acting parties', () => {
        const wrapper = mount(PartiesIndex, {props: {parties}});
        expect(wrapper.find('[data-testid="party-5"] [data-testid="acting-badge"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="party-6"] [data-testid="acting-badge"]').text()).toBe('Acting as Host');
    });

    it('enters and leaves act-as-host', async () => {
        const wrapper = mount(PartiesIndex, {props: {parties}});
        await wrapper.find('[data-testid="party-5"] button').trigger('click');
        expect(router.post).toHaveBeenCalledWith('/admin/parties/5/act-as-host', {}, {preserveScroll: true});

        await wrapper.find('[data-testid="party-6"] button').trigger('click');
        expect(router.delete).toHaveBeenCalledWith('/admin/parties/6/act-as-host', {preserveScroll: true});
    });

    it('labels buttons accessibly', () => {
        const wrapper = mount(PartiesIndex, {props: {parties}});
        expect(wrapper.find('[data-testid="party-5"] button').attributes('aria-label')).toBe('Enter Act-as-Host for LAN One');
        expect(wrapper.find('[data-testid="party-6"] button').attributes('aria-label')).toBe('Leave Act-as-Host for LAN Two');
    });
});

describe('Admin Tokens index', () => {
    const tokens = {data: [
        {id: 1, name: 'Exporter', abilities: ['export'], last_used_at: null, revoked: false},
        {id: 2, name: 'Old', abilities: ['read'], last_used_at: null, revoked: true},
    ]};

    it('lists tokens, revokes active ones only and shows the issued value once', async () => {
        const wrapper = mount(TokensIndex, {props: {tokens, abilities: ['read', 'export'], issued: {name: 'New', value: 'mpi_secret'}}});

        expect(wrapper.get('[data-testid="issued-token"]').text()).toContain('mpi_secret');
        expect(wrapper.get('[data-testid="token-1"]').text()).toContain('Never');
        expect(wrapper.find('[data-testid="token-2"] [data-action="revoke"]').exists()).toBe(false);

        await wrapper.get('[data-testid="token-1"] [data-action="revoke"]').trigger('click');
        expect(router.delete).toHaveBeenCalledWith('/admin/tokens/1', expect.anything());
    });

    it('hides the issued banner when there is no new token', () => {
        const wrapper = mount(TokensIndex, {props: {tokens: {data: []}, abilities: ['read']}});
        expect(wrapper.find('[data-testid="issued-token"]').exists()).toBe(false);
        expect(wrapper.text()).toContain('No integration tokens.');
    });
});
