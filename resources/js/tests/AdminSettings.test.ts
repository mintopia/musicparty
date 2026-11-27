import {mount} from '@vue/test-utils';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import SettingsIndex from '@/pages/Admin/Settings/Index.vue';
import ProviderEdit from '@/pages/Admin/SocialProviders/Edit.vue';

const inertia = vi.hoisted(() => ({patch: vi.fn(), routerPatch: vi.fn()}));

vi.mock('@inertiajs/vue3', async () => {
    const {reactive} = await import('vue');
    return {
        Head: {render: () => null},
        Link: {props: ['href'], template: '<a :href="href"><slot /></a>'},
        router: {patch: inertia.routerPatch},
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
    it('renders settings, disables readonly theme delete, and patches the form', async () => {
        const wrapper = mount(SettingsIndex, {
            props: {
                settings: [
                    {code: 'site', name: 'Site', description: 'd', type: 'string', required: true, value: 'x'},
                    {code: 'flag', name: 'Flag', description: null, type: 'boolean', required: false, value: true},
                ],
                socialProviders: [
                    {
                        id: 3,
                        code: 'discord',
                        name: 'Discord',
                        supports_auth: true,
                        enabled: true,
                        auth_enabled: false,
                        needs_secret: false,
                        login_return_url: 'http://x/login/discord/return',
                        linked_account_url: 'http://x/linked/discord',
                    },
                ],
            },
        });

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
        expect(wrapper.findAll('[role=tab]').map((tab) => tab.text())).toEqual([
            'General',
            'Branding',
            'Sign-in providers',
        ]);
        expect(wrapper.get('[data-test=themes-link]').attributes('href')).toBe('/admin/themes');
        expect(wrapper.find('#panel-themes').exists()).toBe(false);
    });

    it('flags an enabled provider with no secret and warns on the edit form', async () => {
        const row = {
            id: 4,
            code: 'steam',
            name: 'Steam',
            supports_auth: true,
            enabled: true,
            auth_enabled: true,
            needs_secret: true,
            login_return_url: 'http://x/login/steam/return',
            linked_account_url: 'http://x/linked/steam',
        };
        const index = mount(SettingsIndex, {props: {settings: [], socialProviders: [row]}});
        expect(index.text()).toContain('Needs a secret');
        expect(index.text()).toContain('On, but the client secret is empty');

        const edit = mount(ProviderEdit, {
            props: {
                provider: {
                    id: 4,
                    name: 'Steam',
                    can_be_renamed: false,
                    supports_auth: true,
                    enabled: true,
                    auth_enabled: true,
                },
                settings: [{code: 'client_secret', name: 'API Key', description: null, required: true, value: ''}],
            },
        });
        expect(edit.text()).toContain('Client secret');
        expect(edit.get('[data-test=secret-warning]').text()).toContain('Steam sign-in is turned on but has no secret');
        await edit.get('input[name="client_secret"]').setValue('abc');
        expect(edit.find('[data-test=secret-warning]').exists()).toBe(false);
        await edit.get('form').trigger('submit');
        expect(inertia.patch).toHaveBeenCalledWith('/admin/settings/socialproviders/4');
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
