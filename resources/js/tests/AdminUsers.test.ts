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
    avatar_url: 'https://img.test/u.png',
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
    it('renders rows and cards, and searches with chips while preserving order', async () => {
        const wrapper = mount(UserIndex, {
            props: {
                users: {
                    data: [
                        {
                            ...user,
                            is_admin: true,
                            avatar_url: 'https://img.test/a.png',
                            accounts: [
                                {provider: 'discord', name: 'Discord', needs_relink: false},
                                {provider: 'spotify', name: 'Spotify', needs_relink: true},
                            ],
                            parties_count: 3,
                        },
                    ],
                    total: 1,
                    from: 1,
                    to: 1,
                    current_page: 1,
                    last_page: 1,
                    prev_page_url: null,
                    next_page_url: null,
                    links: [],
                },
                filters: {
                    q: 'ali',
                    role: '',
                    suspended: false,
                    first_login: false,
                    provider: '',
                    spotify_relink: false,
                },
                providers: [
                    {code: 'discord', name: 'Discord'},
                    {code: 'spotify', name: 'Spotify'},
                ],
                total: 9,
                order: 'nickname',
                order_direction: 'desc',
            },
        });

        expect(wrapper.find('a[href="/admin/users/7"]').text()).toBe('alice');
        expect(wrapper.text()).toContain('Never');
        expect(wrapper.find('[data-test="users-list"] [aria-label="Actions for alice"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="users-list"]').text()).toContain('3 parties');
        expect(wrapper.find('[data-test="search-count"]').text()).toBe('1 of 9 users');
        expect(wrapper.find('[data-test="user-avatar"]').attributes('style')).toContain('https://img.test/a.png');
        const icons = wrapper.findAll('table [data-test="user-accounts"] i');
        expect(icons.map((icon) => icon.attributes('aria-label'))).toEqual(['Discord', 'Spotify, needs re-link']);
        expect(icons[0].classes()).toContain('ti-brand-discord');
        expect(wrapper.find('tbody td.text-end').text()).toBe('3');

        await wrapper.find('form').trigger('submit');
        expect(inertia.get).toHaveBeenLastCalledWith(
            '/admin/users',
            {order: 'nickname', order_direction: 'desc', q: 'ali'},
            {preserveState: true, replace: true},
        );

        const chips = wrapper.findAll('[data-test="search-chip"]');
        expect(chips.map((chip) => chip.text())).toEqual([
            'Admin',
            'Suspended',
            'Signup not finished',
            'Spotify needs re-link',
            'Discord',
        ]);
        await chips[0]!.trigger('click');
        await chips[4]!.trigger('click');
        expect(inertia.get).toHaveBeenLastCalledWith(
            '/admin/users',
            {order: 'nickname', order_direction: 'desc', q: 'ali', role: 'admin', provider: 'discord'},
            {preserveState: true, replace: true},
        );

        const input = wrapper.get('[data-test="search-input"]');
        expect((input.element as HTMLInputElement).value).toBe('ali role:admin provider:discord');
        expect(input.attributes('placeholder')).toBe(
            'Search by id, nickname or linked account. Try role:admin or provider:discord',
        );
        await input.setValue('id:7 suspended:yes signup:unfinished spotify:relink provider:steam');
        await wrapper.find('form').trigger('submit');
        expect(inertia.get).toHaveBeenLastCalledWith(
            '/admin/users',
            {
                order: 'nickname',
                order_direction: 'desc',
                q: 'id:7',
                suspended: '1',
                first_login: '1',
                spotify_relink: '1',
                provider: 'steam',
            },
            {preserveState: true, replace: true},
        );
        expect(wrapper.findAll('[data-test="search-chip"]').map((chip) => chip.attributes('aria-pressed'))).toEqual([
            'false',
            'true',
            'true',
            'true',
            'false',
        ]);
    });

    it('repeats the query in the empty state with one clear button', () => {
        const wrapper = mount(UserIndex, {
            props: {
                users: {
                    data: [],
                    total: 0,
                    from: null,
                    to: null,
                    current_page: 1,
                    last_page: 1,
                    prev_page_url: null,
                    next_page_url: null,
                    links: [],
                },
                filters: {
                    q: 'id:99',
                    role: '',
                    suspended: true,
                    first_login: false,
                    provider: '',
                    spotify_relink: false,
                },
                providers: [],
                total: 9,
                order: 'id',
                order_direction: 'asc',
            },
        });
        expect(wrapper.find('[data-test="empty-state"] .empty-title code').text()).toBe('id:99 suspended:yes');
        expect(wrapper.find('[data-test="empty-state"]').text()).toContain('\u201cid:99\u201d, Suspended');
        expect(wrapper.findAll('[data-test="clear-filters"]')).toHaveLength(1);
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
                        provider_code: 'discord',
                        linked_human: '1 day ago',
                        linked_at: '2026-01-01 00:00:00',
                        needs_relink: false,
                        can_delete: false,
                    },
                ],
                parties: [],
            },
        });

        const impersonate = wrapper.find('a[href="/admin/users/7/impersonate"]');
        expect(impersonate.exists()).toBe(true);
        expect(wrapper.text()).toContain('This user is suspended');
        expect(wrapper.find('a[href="/admin/users/7/accounts/1"]').exists()).toBe(false);
        expect(wrapper.find('.btn.disabled').exists()).toBe(true);
    });

    it('shows link state and party memberships without a token note', () => {
        const base = {
            id: 1,
            provider: 'Spotify',
            name: 'a',
            email: null,
            avatar_url: null,
            provider_code: 'spotify',
            linked_human: '1 day ago',
            linked_at: null,
            needs_relink: true,
            can_delete: true,
        };
        const wrapper = mount(UserShow, {
            props: {
                user,
                accounts: [base],
                parties: [{code: 'HZMN', name: 'Retro Room', role: 'Owner'}],
            },
        });

        expect(wrapper.text()).not.toContain('Token expire');
        expect(wrapper.text()).toContain('Needs re-link');
        expect(wrapper.text()).toContain('Retro Room');

        const table = wrapper.find('[data-test="parties-table"]');
        expect(table.findAll('th').map((th) => th.text())).toEqual(['Party', 'Role']);
        expect(table.find('.badge').classes()).toContain('bg-blue-lt');
    });

    it('submits the delete forms', async () => {
        const del = mount(UserDelete, {
            props: {
                user: {id: 7, nickname: 'alice'},
                impact: {accounts: ['Discord', 'Spotify'], accounts_count: 2, memberships_count: 1, owned_parties: []},
            },
        });
        expect(del.get('[data-test="delete-impact"]').text()).toBe(
            'Removes 2 linked accounts (Discord, Spotify) and their membership of 1 party.',
        );
        await del.find('form').trigger('submit');
        expect(inertia.delete).toHaveBeenCalledWith('/admin/users/7');

        const account = mount(LinkedAccountDelete, {
            props: {user: {id: 7, nickname: 'alice'}, account: {id: 3, provider: 'Discord'}},
        });
        await account.find('form').trigger('submit');
        expect(inertia.delete).toHaveBeenCalledWith('/admin/users/7/accounts/3');
    });
});
