import {mount} from '@vue/test-utils';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import Dashboard from '@/pages/Admin/Dashboard.vue';
import SettingsIndex from '@/pages/Admin/Settings/Index.vue';
import ProviderEdit from '@/pages/Admin/SocialProviders/Edit.vue';

const inertia = vi.hoisted(() => ({patch: vi.fn()}));

vi.mock('@inertiajs/vue3', async () => {
    const {reactive} = await import('vue');
    return {
        Head: {render: () => null},
        Link: {props: ['href'], template: '<a :href="href"><slot /></a>'},
        useForm: (data: Record<string, unknown>) =>
            reactive({...data, errors: {}, processing: false, patch: inertia.patch}),
    };
});

vi.mock('@/layouts/AppLayout.vue', () => ({
    default: {template: '<div><slot name="breadcrumbs" /><slot /></div>'},
}));

beforeEach(() => {
    vi.clearAllMocks();
});

describe('admin settings pages', () => {
    it('renders dashboard stats', () => {
        const wrapper = mount(Dashboard, {props: {stats: {users: {total: 5, lastWeek: 2}}}});
        expect(wrapper.text()).toContain('5 Users');
        expect(wrapper.text()).toContain('2 in the last week');
    });

    it('renders settings, disables readonly theme delete, and patches the form', async () => {
        const wrapper = mount(SettingsIndex, {
            props: {
                settings: [
                    {code: 'site', name: 'Site', description: 'd', type: 'string', required: true, value: 'x'},
                    {code: 'flag', name: 'Flag', description: null, type: 'boolean', required: false, value: true},
                ],
                themes: [
                    {id: 1, name: 'Core', active: true, readonly: true},
                    {id: 2, name: 'Mine', active: false, readonly: false},
                ],
                socialProviders: [
                    {
                        id: 3,
                        code: 'discord',
                        name: 'Discord',
                        supports_auth: true,
                        enabled: true,
                        auth_enabled: false,
                        login_return_url: 'http://x/login/discord/return',
                        linked_account_url: 'http://x/linked/discord',
                    },
                ],
            },
        });

        expect(wrapper.find('a[href="/admin/settings/themes/1/delete"]').exists()).toBe(false);
        expect(wrapper.find('a[href="/admin/settings/themes/2/delete"]').exists()).toBe(true);
        expect(wrapper.find('a[href="/admin/settings/socialproviders/3/edit"]').exists()).toBe(true);
        const copyButtons = wrapper.findAllComponents({name: 'CopyButton'});
        expect(copyButtons.map((button) => button.props('value'))).toEqual([
            'http://x/login/discord/return',
            'http://x/linked/discord',
        ]);
        expect(wrapper.text()).toContain('Linking only');
        expect(wrapper.find('[data-test="save-bar"]').exists()).toBe(false);
        await wrapper.find('input[name="site"]').setValue('changed');
        expect(wrapper.find('[data-test="save-bar"]').text()).toContain('1 unsaved change');
        await wrapper.find('form').trigger('submit');
        expect(inertia.patch).toHaveBeenCalledWith('/admin/settings');
    });

    it('shows rename and auth fields only when supported and patches the provider', async () => {
        const wrapper = mount(ProviderEdit, {
            props: {
                provider: {
                    id: 3,
                    name: 'Discord',
                    can_be_renamed: false,
                    supports_auth: false,
                    enabled: true,
                    auth_enabled: false,
                },
                settings: [{code: 'client_id', name: 'Client ID', description: null, required: true, value: 'abc'}],
            },
        });

        expect(wrapper.find('input[name="name"]').exists()).toBe(false);
        expect(wrapper.find('input[name="auth_enabled"]').exists()).toBe(false);
        expect((wrapper.find('input[name="client_id"]').element as HTMLInputElement).value).toBe('abc');
        await wrapper.find('form').trigger('submit');
        expect(inertia.patch).toHaveBeenCalledWith('/admin/settings/socialproviders/3');
    });
});
