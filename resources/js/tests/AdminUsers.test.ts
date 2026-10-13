import {mount} from '@vue/test-utils';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import LinkedAccountDelete from '@/pages/Admin/LinkedAccounts/Delete.vue';
import UserDelete from '@/pages/Admin/Users/Delete.vue';
import UserIndex from '@/pages/Admin/Users/Index.vue';
import UserShow from '@/pages/Admin/Users/Show.vue';

const inertia = vi.hoisted(() => ({get: vi.fn(), delete: vi.fn()}));

vi.mock('@inertiajs/vue3', async () => {
    const {reactive} = await import('vue');
    return {
        Head: {render: () => null},
        Link: {props: ['href'], template: '<a :href="href"><slot /></a>'},
        router: inertia,
        useForm: (data: Record<string, unknown>) =>
            reactive({
                ...data,
                errors: {},
                processing: false,
                delete: inertia.delete,
            }),
    };
});

vi.mock('@/layouts/AppLayout.vue', () => ({
    default: {template: '<div><slot name="breadcrumbs" /><slot /></div>'},
}));

const user = {
    id: 7,
    nickname: 'alice',
    email: null,
    suspended: true,
    first_login: false,
    terms_agreed: true,
    roles: [],
    last_login: null,
    last_login_human: null,
    created_at: '2026-01-01 00:00:00',
    created_at_human: '1 month ago',
};

beforeEach(() => {
    vi.clearAllMocks();
});

describe('admin users', () => {
    it('renders the index rows with a Never fallback and preserves order on search', async () => {
        const wrapper = mount(UserIndex, {
            props: {
                users: {
                    data: [{...user}],
                    current_page: 1,
                    last_page: 1,
                    prev_page_url: null,
                    next_page_url: null,
                    links: [],
                },
                filters: {id: null, nickname: 'ali'},
                order: 'nickname',
                order_direction: 'desc',
            },
        });

        expect(wrapper.find('a[href="/admin/users/7"]').text()).toBe('alice');
        expect(wrapper.text()).toContain('Never');
        await wrapper.find('form').trigger('submit');
        expect(inertia.get).toHaveBeenCalledWith('/admin/users', {
            order: 'nickname',
            order_direction: 'desc',
            nickname: 'ali',
        });
    });

    it('uses a plain anchor for impersonation and disables undeletable accounts', () => {
        const wrapper = mount(UserShow, {
            props: {
                user,
                accounts: [
                    {
                        id: 1,
                        provider: 'Discord',
                        name: 'a',
                        email: null,
                        avatar_url: null,
                        external_id: 'x',
                        can_delete: false,
                    },
                ],
            },
        });

        const impersonate = wrapper.find('a[href="/admin/users/7/impersonate"]');
        expect(impersonate.exists()).toBe(true);
        expect(wrapper.text()).toContain('This user is suspended');
        expect(wrapper.find('a[href="/admin/users/7/accounts/1"]').exists()).toBe(false);
        expect(wrapper.find('.btn.disabled').exists()).toBe(true);
    });

    it('submits the delete forms', async () => {
        const del = mount(UserDelete, {props: {user: {id: 7, nickname: 'alice'}}});
        await del.find('form').trigger('submit');
        expect(inertia.delete).toHaveBeenCalledWith('/admin/users/7');

        const account = mount(LinkedAccountDelete, {
            props: {user: {id: 7, nickname: 'alice'}, account: {id: 3, provider: 'Discord'}},
        });
        await account.find('form').trigger('submit');
        expect(inertia.delete).toHaveBeenCalledWith('/admin/users/7/accounts/3');
    });
});
