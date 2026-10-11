import {beforeEach, describe, expect, it, vi} from 'vitest';
import {mount} from '@vue/test-utils';
import {h} from 'vue';

const page = vi.hoisted(() => ({props: {}, url: '/admin/settings'}));
const post = vi.hoisted(() => vi.fn());

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => page,
    router: {},
    useForm: (data) => ({...data, errors: {}, processing: false, post}),
    Head: {render: () => null},
    Link: {props: ['href'], render() { return h('a', {href: this.href}, this.$slots.default?.()); }},
}));

import Settings from '../pages/Admin/Settings.vue';

const provider = (extra) => ({
    code: 'discord', name: 'Discord', enabled: true, supports_auth: true, auth_enabled: true,
    return_urls: ['https://mp.test/login/discord/return'], fields: [], ...extra,
});

const mountSettings = (providers) => mount(Settings, {
    props: {
        settings: {name: 'Music Party', cover_image_url: null},
        providers,
        theme: {light: {primary: '#111111'}, dark: {primary: '#222222'}, font: 'Inter'},
    },
});

beforeEach(() => post.mockReset());

describe('Admin Settings', () => {
    it('renders provider status pills, return urls and edit links', () => {
        const wrapper = mountSettings([
            provider(),
            provider({code: 'spotifysearch', name: 'Spotify Search', enabled: false, supports_auth: false, auth_enabled: false, return_urls: ['https://mp.test/login/spotifysearch/return']}),
        ]);

        const discord = wrapper.find('[data-testid="provider-discord"]');
        expect(discord.text()).toContain('https://mp.test/login/discord/return');
        expect(discord.find('a').attributes('href')).toBe('/admin/providers/discord/edit');

        const search = wrapper.find('[data-testid="provider-spotifysearch"]');
        expect(search.text()).toContain('N/A');
    });

    it('shows the theme card with swatches, font and an edit link', () => {
        const wrapper = mountSettings([provider()]);
        const card = wrapper.find('[data-testid="theme-card"]');

        expect(card.find('[data-swatch="light-primary"]').attributes('style')).toContain('background-color');
        expect(card.find('[data-testid="theme-font"]').text()).toBe('Inter');
        expect(card.find('a[href="/admin/theme"]').text()).toContain('Edit theme');
    });

    it('lists the cover image field and posts the form', async () => {
        const wrapper = mountSettings([]);

        expect(wrapper.find('#cover_image').exists()).toBe(true);
        await wrapper.find('form').trigger('submit');
        expect(post).toHaveBeenCalledWith('/admin/settings', expect.objectContaining({forceFormData: true}));
    });
});
