import {mount} from '@vue/test-utils';
import {describe, expect, it, vi} from 'vitest';
import {reactive} from 'vue';
import Home from '@/pages/Home.vue';
import Edit from '@/pages/Users/Edit.vue';
import LinkedAccountDelete from '@/pages/Users/LinkedAccountDelete.vue';
import JoinSteps from '@/components/JoinSteps.vue';
import PartyInviteCard from '@/components/PartyInviteCard.vue';
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
            clearErrors: () => undefined,
        }) as Record<string, unknown>;
        for (const method of ['put', 'patch', 'delete', 'post']) {
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
vi.mock('@/routes/parties', () => ({create: () => ({url: '/parties/create'}), join: () => ({url: '/parties/join'})}));
vi.mock('@/routes/user', () => ({profile: () => ({url: '/profile'}), spotify: () => ({url: '/profile/spotify'})}));
vi.mock('@/routes/user/profile', () => ({
    edit: () => ({url: '/profile/edit'}),
    update: () => ({url: '/profile'}),
}));

const user = {nickname: 'Ada', email: 'ada@example.test', avatar: 'https://img.test/a.png'};

describe('user pages', () => {
    it('renders the home party list with join and create', () => {
        const wrapper = mount(Home, {
            props: {
                parties: [
                    {
                        code: 'KXQB',
                        name: 'Coyote LAN 26',
                        role: 'Owner',
                        roleCode: 'owner',
                        playback: 'playing',
                        detail: 'Midnight City, M83',
                        cover: null,
                        canModerate: true,
                        url: '/parties/KXQB/dashboard',
                    },
                ],
            },
        });
        expect(wrapper.find('h1').text()).toBe('Your parties');
        expect(wrapper.text()).toContain('Coyote LAN 26');
        expect(wrapper.text()).toContain('Owner');
        expect(wrapper.text()).toContain('Playing');
        expect(wrapper.find('input.mp-code-input').exists()).toBe(true);
        expect(wrapper.find('a[href="/parties/create"]').exists()).toBe(true);
    });

    it('shows guests online on the invite card with singular and plural', () => {
        const party = {
            code: 'KXQB',
            name: 'Coyote LAN 26',
            host: 'Jess',
            online: 1,
            playback: 'idle' as const,
            detail: null,
            cover: null,
        };
        expect(mount(PartyInviteCard, {props: {party}}).text()).toContain('Hosted by Jess, 1 guest online');
        expect(mount(PartyInviteCard, {props: {party: {...party, online: 5}}}).text()).toContain(
            'Hosted by Jess, 5 guests online',
        );
    });

    it('renders a login button per provider', () => {
        const wrapper = mount(Login, {props: {providers: [{code: 'discord', name: 'Discord'}]}});
        const link = wrapper.find('a.btn-discord');
        expect(link.attributes('href')).toBe('/login/discord');
        expect(link.find('i').classes()).toContain('ti-brand-discord');
        expect(link.text()).toBe('Login with Discord');
    });

    it('shows the steps without party details when arriving from a party', () => {
        const wrapper = mount(Login, {props: {providers: [{code: 'discord', name: 'Discord'}], fromParty: true}});
        expect(wrapper.findComponent(JoinSteps).exists()).toBe(true);
        expect(wrapper.findComponent(PartyInviteCard).exists()).toBe(false);
        expect(wrapper.text()).not.toContain('straight back');
        expect(wrapper.find('a.btn-discord').exists()).toBe(true);
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
        expect(wrapper.find('a.btn-link').attributes('method')).toBe('post');
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
        expect(wrapper.find('[aria-label="Spotify Avatar"]').attributes('style')).toContain('/s.png');
        expect(wrapper.find('a[href="/profile/accounts/github/link"]').text()).toContain('GitHub');
        expect(wrapper.find('a[href="/profile/spotify"]').exists()).toBe(true);
        expect(wrapper.find('a.btn-primary').attributes('href')).toBe('/profile/edit');
    });

    it('enables Create Party only when both spotify links exist', () => {
        const partial = mount(Spotify, {
            props: {spotify: {name: 'Ada'}, search: null, spotifyEnabled: true, searchEnabled: true},
        });
        expect(partial.find('button[disabled]').exists()).toBe(true);
        expect(partial.find('a[href="/profile/accounts/spotifysearch/link"]').exists()).toBe(true);

        const full = mount(Spotify, {
            props: {spotify: {name: 'Ada'}, search: {name: 'Ada S'}, spotifyEnabled: true, searchEnabled: true},
        });
        expect(full.find('a.btn-primary').attributes('href')).toBe('/parties/create');
        expect(partial.text()).toContain('Search still needs linking.');
        expect(partial.text()).toContain('Link Spotify for search');
        expect(full.text()).toContain('Ada S');
    });

    it('hides link buttons for disabled providers', () => {
        const wrapper = mount(Spotify, {
            props: {spotify: null, search: null, spotifyEnabled: false, searchEnabled: true},
        });
        expect(wrapper.find('a[href="/profile/accounts/spotify/link"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="link-playback-disabled"]').exists()).toBe(true);
        expect(wrapper.find('a[href="/profile/accounts/spotifysearch/link"]').exists()).toBe(true);
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
        expect(wrapper.find('h1').text()).toBe('Unlink Discord');
        await wrapper.find('form').trigger('submit');
        expect(inertia.submitted[0]).toMatchObject({method: 'delete', url: '/profile/accounts/7'});
    });
});
