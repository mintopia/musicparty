import {mount} from '@vue/test-utils';
import {describe, expect, it, vi} from 'vitest';
import {reactive} from 'vue';
import Home from '@/pages/Home.vue';
import Edit from '@/pages/Users/Edit.vue';
import LinkedAccountDelete from '@/pages/Users/LinkedAccountDelete.vue';
import Login from '@/pages/Users/Login.vue';
import Profile from '@/pages/Users/Profile.vue';
import Signup from '@/pages/Users/Signup.vue';
import Spotify from '@/pages/Users/Spotify.vue';

const inertia = vi.hoisted(() => ({
    submitted: [] as {method: string; url: string; data: Record<string, unknown>}[],
    errors: {} as Record<string, string>,
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: {render: () => null},
    Link: {props: ['href'], template: '<a :href="href"><slot /></a>'},
    useForm: (initial: Record<string, unknown>) => {
        const form = reactive({
            ...initial,
            errors: inertia.errors,
            processing: false,
        }) as Record<string, unknown>;
        for (const method of ['put', 'patch', 'delete']) {
            form[method] = (url: string) => {
                const data = Object.fromEntries(Object.keys(initial).map((key) => [key, form[key]]));
                inertia.submitted.push({method, url, data});
            };
        }
        return form;
    },
}));

vi.mock('@/layouts/AppLayout.vue', () => ({
    default: {template: '<div><slot name="breadcrumbs" /><slot /></div>'},
}));
vi.mock('@/layouts/GuestLayout.vue', () => ({default: {template: '<div><slot /></div>'}}));

vi.mock('@/routes', () => ({home: () => ({url: '/'}), logout: () => ({url: '/logout'})}));
vi.mock('@/routes/login', () => ({
    redirect: (code: string) => ({url: `/login/${code}`}),
    signup: () => ({url: '/login/signup'}),
}));
vi.mock('@/routes/linkedaccounts', () => ({
    create: (code: string) => ({url: `/profile/accounts/${code}/link`}),
    deleteMethod: (id: number) => ({url: `/profile/accounts/${id}/delete`}),
    destroy: (id: number) => ({url: `/profile/accounts/${id}`}),
}));
vi.mock('@/routes/parties', () => ({create: () => ({url: '/parties/create'})}));
vi.mock('@/routes/user', () => ({profile: () => ({url: '/profile'})}));
vi.mock('@/routes/user/profile', () => ({
    edit: () => ({url: '/profile/edit'}),
    update: () => ({url: '/profile'}),
}));

const user = {nickname: 'Ada', email: 'ada@example.test', avatar: 'https://img.test/a.png'};

describe('user pages', () => {
    it('renders the home profile card', () => {
        const wrapper = mount(Home, {props: {user}});
        expect(wrapper.text()).toContain('Ada');
        expect(wrapper.text()).toContain('ada@example.test');
        expect(wrapper.find('.avatar').attributes('style')).toContain('https://img.test/a.png');
    });

    it('renders a login button per provider', () => {
        const wrapper = mount(Login, {props: {providers: [{code: 'discord', name: 'Discord'}]}});
        const link = wrapper.find('a.btn-discord');
        expect(link.attributes('href')).toBe('/login/discord');
        expect(link.find('i').classes()).toContain('ti-brand-discord');
        expect(link.text()).toBe('Login with Discord');
    });

    it('submits signup with PUT and shows terms links and errors', async () => {
        inertia.submitted.length = 0;
        inertia.errors = {terms: 'You must accept'};
        const wrapper = mount(Signup, {
            props: {user, terms: '/terms', privacy: '/privacy'},
        });
        expect(wrapper.text()).toContain('Terms and Conditions');
        expect(wrapper.text()).toContain('Privacy Policy');
        expect(wrapper.find('.invalid-feedback').text()).toBe('You must accept');
        expect(wrapper.find('a.btn-link').attributes('href')).toBe('/logout');
        await wrapper.find('input[name=terms]').setValue(true);
        await wrapper.find('form').trigger('submit');
        expect(inertia.submitted[0]).toMatchObject({method: 'put', url: '/login/signup'});
        expect(inertia.submitted[0].data.nickname).toBe('Ada');
        inertia.errors = {};
    });

    it('hides the terms checkbox when no documents are configured', () => {
        const wrapper = mount(Signup, {props: {user, terms: null, privacy: null}});
        expect(wrapper.find('input[name=terms]').exists()).toBe(false);
    });

    it('lists linked accounts with remove only when allowed', () => {
        const wrapper = mount(Profile, {
            props: {
                user,
                accounts: [
                    {
                        id: 1,
                        providerCode: 'discord',
                        providerName: 'Discord',
                        name: 'ada',
                        avatarUrl: null,
                        canDelete: true,
                    },
                    {
                        id: 2,
                        providerCode: 'spotify',
                        providerName: 'Spotify',
                        name: 'ada_s',
                        avatarUrl: '/s.png',
                        canDelete: false,
                    },
                ],
                availableLinks: [{code: 'github', name: 'GitHub'}],
            },
        });
        const removes = wrapper.findAll('a.btn-outline-danger');
        expect(removes).toHaveLength(1);
        expect(removes[0].attributes('href')).toBe('/profile/accounts/1/delete');
        expect(wrapper.find('img').attributes('src')).toBe('/s.png');
        expect(wrapper.find('a.btn-github').attributes('href')).toBe('/profile/accounts/github/link');
        expect(wrapper.find('a.btn-primary').attributes('href')).toBe('/profile/edit');
    });

    it('enables Create Party only when both spotify links exist', () => {
        const partial = mount(Spotify, {props: {spotify: {name: 'Ada'}, search: null}});
        expect(partial.find('button[disabled]').exists()).toBe(true);
        expect(partial.find('a[href="/profile/accounts/spotifysearch/link"]').exists()).toBe(true);

        const full = mount(Spotify, {props: {spotify: {name: 'Ada'}, search: {name: 'Ada S'}}});
        expect(full.find('a.btn-primary').attributes('href')).toBe('/parties/create');
        expect(full.text()).toContain('Ada S');
    });

    it('submits the profile edit with PATCH', async () => {
        inertia.submitted.length = 0;
        const wrapper = mount(Edit, {props: {user: {nickname: 'Ada'}}});
        await wrapper.find('input[name=nickname]').setValue('Grace');
        await wrapper.find('form').trigger('submit');
        expect(inertia.submitted[0]).toMatchObject({method: 'patch', url: '/profile', data: {nickname: 'Grace'}});
    });

    it('confirms linked account removal with DELETE', async () => {
        inertia.submitted.length = 0;
        const wrapper = mount(LinkedAccountDelete, {props: {account: {id: 7, providerName: 'Discord'}}});
        expect(wrapper.find('h1').text()).toBe('Remove Linked Discord Account');
        await wrapper.find('form').trigger('submit');
        expect(inertia.submitted[0]).toMatchObject({method: 'delete', url: '/profile/accounts/7'});
    });
});
