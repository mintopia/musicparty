import {beforeEach, describe, expect, it, vi} from 'vitest';
import {mount} from '@vue/test-utils';
import {h} from 'vue';

const page = vi.hoisted(() => ({props: {}, url: '/'}));
const router = vi.hoisted(() => ({get: vi.fn(), post: vi.fn(), delete: vi.fn()}));

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => page,
    router,
    Head: {render: () => null},
    Link: {props: ['href'], render() { return h('a', {href: this.href}, this.$slots.default?.()); }},
}));

import UsersIndex from '../pages/Admin/Users/Index.vue';

beforeEach(() => {
    router.get.mockReset();
    page.props = {appName: 'MP', auth: {user: {id: 1, is_admin: true}}, errors: {}};
    page.url = '/admin/users';
});

const users = (extra = {}) => ({
    data: [
        {id: 1, nickname: 'Ann', suspended: false, roles: ['admin'], last_login: null, created_at: '2024-01-01T00:00:00Z'},
        {id: 2, nickname: 'Bob', suspended: true, roles: [], last_login: '2025-01-01T00:00:00Z', created_at: '2024-01-02T00:00:00Z'},
    ],
    links: [
        {url: null, label: '&laquo; Previous', active: false},
        {url: '/admin/users?page=1', label: '1', active: true},
        {url: '/admin/users?page=2', label: '2', active: false},
        {url: '/admin/users?page=2', label: 'Next &raquo;', active: false},
    ],
    meta: {from: 1, to: 2, total: 22, last_page: 2, links: [
        {url: null, label: '&laquo; Previous', active: false},
        {url: '/admin/users?page=1', label: '1', active: true},
        {url: '/admin/users?page=2', label: '2', active: false},
        {url: '/admin/users?page=2', label: 'Next &raquo;', active: false},
    ]},
    ...extra,
});

describe('Admin Users index', () => {
    const mountUsers = (props = {}) => mount(UsersIndex, {props: {users: users(), filters: {}, ...props}});

    it('renders rows linking to the user page', () => {
        const wrapper = mountUsers();
        const ann = wrapper.find('[data-testid="user-1"]');

        expect(ann.find('a[href="/admin/users/1"]').text()).toBe('Ann');
        expect(wrapper.find('[data-testid="user-2"]').text()).toContain('Bob');
        expect(wrapper.find('[data-testid="user-1"]').text()).toContain('Never');
    });

    it('has sortable id, nickname, last login and created headers', () => {
        const sorts = mountUsers().findAll('[data-sort]').map((link) => link.attributes('data-sort'));

        expect(sorts).toEqual(['id', 'nickname', 'last_login', 'created_at']);
    });

    it('searches by id and nickname via GET', async () => {
        const wrapper = mountUsers({filters: {nickname: 'an'}});
        expect(wrapper.find('#search-nickname').element.value).toBe('an');

        await wrapper.find('#search-id').setValue('7');
        await wrapper.find('form').trigger('submit');

        expect(router.get).toHaveBeenCalledWith('/admin/users', {id: '7', nickname: 'an'}, {preserveState: true});
    });

    it('shows the entries summary and pagination', () => {
        const wrapper = mountUsers();

        expect(wrapper.find('[data-testid="pagination-summary"]').text()).toBe('Showing 1 to 2 of 22 entries');
        expect(wrapper.find('nav[aria-label="Pagination"] a[href="/admin/users?page=2"]').exists()).toBe(true);
    });

    it('shows the empty state without pagination', () => {
        const wrapper = mountUsers({users: users({data: [], meta: {from: null, to: null, total: 0, last_page: 1, links: []}})});

        expect(wrapper.text()).toContain('No users found.');
        expect(wrapper.find('nav[aria-label="Pagination"]').exists()).toBe(false);
    });
});
