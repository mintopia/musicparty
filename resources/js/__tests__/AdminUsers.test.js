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

import UsersIndex from '../pages/Admin/Users/Index.vue';

beforeEach(() => {
    vi.useFakeTimers();
    router.get.mockReset();
    router.post.mockReset();
    router.delete.mockReset();
    page.props = {appName: 'MP', auth: {user: {id: 1, is_admin: true}}, parties: [], errors: {}};
    page.url = '/admin/users';
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
