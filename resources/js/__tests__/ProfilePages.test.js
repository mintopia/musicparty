import {beforeEach, describe, expect, it, vi} from 'vitest';
import {mount} from '@vue/test-utils';
import {h, reactive} from 'vue';

const page = vi.hoisted(() => ({props: {}, url: '/'}));
const forms = vi.hoisted(() => []);

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => page,
    router: {},
    useForm: (data) => {
        const form = reactive({...data, errors: {}, processing: false, patch: vi.fn(), delete: vi.fn()});
        forms.push(form);
        return form;
    },
    Head: {render: () => null},
    Link: {props: ['href'], render() { return h('a', {href: this.href}, this.$slots.default?.()); }},
}));

import ProfileShow from '../pages/Profile/Show.vue';
import ProfileEdit from '../pages/Profile/Edit.vue';
import ProfileDeleteAccount from '../pages/Profile/DeleteAccount.vue';

const accounts = [
    {id: 1, provider: 'Discord', provider_code: 'discord', name: 'ravebot', avatar_url: 'https://cdn.example.test/a.png', can_delete: true},
    {id: 2, provider: 'Twitch', provider_code: 'twitch', name: 'streamer', avatar_url: null, can_delete: false},
];

beforeEach(() => {
    forms.length = 0;
    page.props = {errors: {}};
});

describe('Profile/Show', () => {
    const mountShow = (extra = {}) => mount(ProfileShow, {
        props: {
            profile: {nickname: 'partygoer', email: 'me@example.test', avatar_url: 'https://cdn.example.test/me.png'},
            accounts,
            available_links: [{code: 'google', name: 'Google', url: '/profile/accounts/google/link'}],
            ...extra,
        },
    });

    it('shows the profile details and avatar', () => {
        const wrapper = mountShow();
        expect(wrapper.text()).toContain('partygoer');
        expect(wrapper.text()).toContain('me@example.test');
        expect(wrapper.get('[data-testid="profile-avatar"]').attributes('src')).toBe('https://cdn.example.test/me.png');
        expect(wrapper.get('[data-action="edit"]').attributes('href')).toBe('/profile/edit');
    });

    it('lists linked accounts and only enables removal where allowed', () => {
        const wrapper = mountShow();
        const first = wrapper.get('[data-testid="account-1"]');
        const second = wrapper.get('[data-testid="account-2"]');
        expect(first.text()).toContain('ravebot');
        expect(first.get('[data-action="remove-account"]').attributes('href')).toBe('/profile/accounts/1/delete');
        expect(second.get('[data-action="remove-account"]').attributes('disabled')).toBeDefined();
    });

    it('offers links for unlinked providers', () => {
        const wrapper = mountShow();
        const link = wrapper.get('[data-link="google"]');
        expect(link.attributes('href')).toBe('/profile/accounts/google/link');
        expect(link.text()).toContain('Link with Google');
    });

    it('hides link buttons when every provider is linked', () => {
        expect(mountShow({available_links: []}).find('[data-link]').exists()).toBe(false);
    });
});

describe('Profile/Edit', () => {
    it('submits the nickname with a patch and shows validation errors', async () => {
        const wrapper = mount(ProfileEdit, {props: {nickname: 'old'}});
        forms[0].errors = {nickname: 'Please choose a nickname.'};
        await wrapper.vm.$nextTick();
        expect(wrapper.text()).toContain('Please choose a nickname.');
        await wrapper.get('input#nickname').setValue('DJ Fresh');
        await wrapper.get('form').trigger('submit');
        expect(forms[0].nickname).toBe('DJ Fresh');
        expect(forms[0].patch).toHaveBeenCalledWith('/profile');
        expect(wrapper.get('[data-action="cancel"]').attributes('href')).toBe('/profile');
    });
});

describe('Profile/DeleteAccount', () => {
    it('confirms before deleting the linked account', async () => {
        const wrapper = mount(ProfileDeleteAccount, {
            props: {account: {id: 7, provider: 'Discord', name: 'ravebot', email: 'me@example.test'}},
        });
        expect(wrapper.text()).toContain('Discord');
        expect(wrapper.text()).toContain('ravebot');
        await wrapper.get('form').trigger('submit');
        expect(forms[0].delete).toHaveBeenCalledWith('/profile/accounts/7');
        expect(wrapper.get('[data-action="cancel"]').attributes('href')).toBe('/profile');
    });
});
