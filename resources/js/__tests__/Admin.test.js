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
import UsersIndex from '../pages/Admin/Users/Index.vue';
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

describe('SidebarNav admin link', () => {
    it('shows for admins', () => {
        expect(mount(SidebarNav).find('a[href="/admin"]').exists()).toBe(true);
    });

    it('is hidden for non-admins and guests', () => {
        page.props.auth.user.is_admin = false;
        expect(mount(SidebarNav).find('a[href="/admin"]').exists()).toBe(false);
        page.props.auth.user = null;
        expect(mount(SidebarNav).find('a[href="/admin"]').exists()).toBe(false);
    });
});

describe('Admin Dashboard', () => {
    it('links to horizon and pulse only', () => {
        const wrapper = mount(Dashboard, {props: {links: {horizon: '/horizon', pulse: '/pulse'}}});

        expect(wrapper.find('a[href="/horizon"]').exists()).toBe(true);
        expect(wrapper.find('a[href="/pulse"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="card-telescope"]').exists()).toBe(false);
    });
});

const users = (extra = {}) => ({
    data: [
        {id: 1, nickname: 'Ann', suspended: false, roles: ['admin']},
        {id: 2, nickname: 'Bob', suspended: true, roles: []},
    ],
    links: [
        {url: null, label: '&laquo; Previous', active: false},
        {url: '/admin/users?page=1', label: '1', active: true},
        {url: '/admin/users?page=2', label: '2', active: false},
    ],
    current_page: 1,
    last_page: 2,
    ...extra,
});

describe('Admin Users', () => {
    const mountUsers = (props = {}) => mount(UsersIndex, {props: {users: users(), filters: {search: ''}, ...props}});

    it('renders rows with status and role state', () => {
        const wrapper = mountUsers();
        const ann = wrapper.find('[data-testid="user-1"]');
        const bob = wrapper.find('[data-testid="user-2"]');

        expect(ann.text()).toContain('Active');
        expect(bob.text()).toContain('Suspended');
        expect(ann.find('[data-role="admin"]').element.checked).toBe(true);
        expect(ann.find('[data-role="create-party"]').element.checked).toBe(false);
        expect(ann.find('[data-action="suspend"]').text()).toBe('Suspend');
        expect(bob.find('[data-action="suspend"]').text()).toBe('Unsuspend');
    });

    it('suspends and unsuspends', async () => {
        const wrapper = mountUsers();
        await wrapper.find('[data-testid="user-1"] [data-action="suspend"]').trigger('click');
        expect(router.post).toHaveBeenCalledWith('/admin/users/1/suspend', {}, {preserveScroll: true});

        await wrapper.find('[data-testid="user-2"] [data-action="suspend"]').trigger('click');
        expect(router.delete).toHaveBeenCalledWith('/admin/users/2/suspend', {preserveScroll: true});
    });

    it('grants and revokes roles', async () => {
        const wrapper = mountUsers();
        await wrapper.find('[data-testid="user-1"] [data-role="create-party"]').trigger('change');
        expect(router.post).toHaveBeenCalledWith('/admin/users/1/roles', {role: 'create-party'}, {preserveScroll: true});

        await wrapper.find('[data-testid="user-1"] [data-role="admin"]').trigger('change');
        expect(router.delete).toHaveBeenCalledWith('/admin/users/1/roles/admin', {preserveScroll: true});
    });

    it('debounces search into a single GET', async () => {
        const wrapper = mountUsers();
        const input = wrapper.find('#user-search');
        await input.setValue('a');
        await input.setValue('an');
        expect(router.get).not.toHaveBeenCalled();

        vi.advanceTimersByTime(300);
        expect(router.get).toHaveBeenCalledTimes(1);
        expect(router.get).toHaveBeenCalledWith('/admin/users', {search: 'an'}, {preserveState: true, replace: true});
    });

    it('shows admin errors', () => {
        page.props.errors = {admin: 'Cannot remove the last admin.'};
        expect(mountUsers().find('[role="alert"]').text()).toBe('Cannot remove the last admin.');
    });

    it('has a labelled search box and no alert without errors', () => {
        const wrapper = mountUsers();
        expect(wrapper.find('label[for="user-search"]').exists()).toBe(true);
        expect(wrapper.find('[role="alert"]').exists()).toBe(false);
    });

    it('renders pagination links and marks the current page', () => {
        const wrapper = mountUsers();
        const nav = wrapper.find('nav[aria-label="Pagination"]');
        expect(nav.find('a[href="/admin/users?page=2"]').exists()).toBe(true);
        expect(nav.find('a[aria-current="page"]').text()).toBe('1');
    });

    it('omits pagination for a single page and shows empty state', () => {
        const wrapper = mountUsers({users: users({data: [], last_page: 1})});
        expect(wrapper.find('nav[aria-label="Pagination"]').exists()).toBe(false);
        expect(wrapper.text()).toContain('No users found.');
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
