import {beforeEach, describe, expect, it, vi} from 'vitest';
import {mount} from '@vue/test-utils';
import {h} from 'vue';

const page = vi.hoisted(() => ({props: {}, url: '/admin/settings'}));
const post = vi.hoisted(() => vi.fn());
const put = vi.hoisted(() => vi.fn());

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => page,
    router: {put},
    useForm: (data) => ({...data, errors: {}, processing: false, post}),
    Head: {render: () => null},
    Link: {props: ['href'], render() { return h('a', {href: this.href}, this.$slots.default?.()); }},
}));

import Settings from '../pages/Admin/Settings.vue';

const provider = (extra) => ({
    code: 'discord', name: 'Discord', enabled: true, supports_auth: true, auth_enabled: true,
    return_urls: ['https://mp.test/login/discord/return'], fields: [], ...extra,
});

const mountSettings = (providers, extra = {}) => mount(Settings, {
    props: {
        settings: {name: 'Music Party', cover_image_url: null},
        providers,
        ...extra,
        theme: {light: {primary: '#111111'}, dark: {primary: '#222222'}, font: 'Inter'},
    },
});

beforeEach(() => {
    post.mockReset();
    put.mockReset();
});

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

    describe('availability cards', () => {
        const musicProvider = (extra) => ({id: 'spotify', label: 'Spotify', enabled: true, configured: true, missing: [], parties: 0, ...extra});
        const player = (extra) => ({kind: 'web', label: 'Web', enabled: false, compatibleProviders: ['Spotify'], parties: 0, ...extra});

        it('shows pills, toggle labels and compatible providers', () => {
            const wrapper = mountSettings([], {musicProviders: [musicProvider()], players: [player()]});

            const row = wrapper.find('[data-testid="music-provider-spotify"]');
            expect(row.text()).toContain('Yes');
            expect(row.find('[data-testid="toggle"]').text()).toBe('Disable');

            const playerRow = wrapper.find('[data-testid="player-web"]');
            expect(playerRow.text()).toContain('Spotify');
            expect(playerRow.find('[data-testid="toggle"]').text()).toBe('Enable');
        });

        it('links to the provider settings when credentials are missing', () => {
            const wrapper = mountSettings([], {musicProviders: [musicProvider({configured: false, missing: [{code: 'spotify', editUrl: '/admin/providers/spotify/edit'}]})]});

            const row = wrapper.find('[data-testid="music-provider-spotify"]');
            expect(row.text()).toContain('No');
            expect(row.find('a').attributes('href')).toBe('/admin/providers/spotify/edit');
        });

        it('enables without confirming and puts the new state', async () => {
            const confirm = vi.spyOn(window, 'confirm');
            const wrapper = mountSettings([], {players: [player({parties: 3})]});

            await wrapper.find('[data-testid="player-web"] [data-testid="toggle"]').trigger('click');

            expect(confirm).not.toHaveBeenCalled();
            expect(put).toHaveBeenCalledWith('/admin/players/web', {enabled: true}, expect.anything());
        });

        it('confirms before disabling something Parties use', async () => {
            const confirm = vi.spyOn(window, 'confirm').mockReturnValueOnce(false).mockReturnValueOnce(true);
            const wrapper = mountSettings([], {musicProviders: [musicProvider({parties: 2})]});
            const toggle = wrapper.find('[data-testid="music-provider-spotify"] [data-testid="toggle"]');

            await toggle.trigger('click');
            expect(confirm).toHaveBeenCalledWith('2 parties use this. They keep working, but no new party can choose it.');
            expect(put).not.toHaveBeenCalled();

            await toggle.trigger('click');
            expect(put).toHaveBeenCalledWith('/admin/music-providers/spotify', {enabled: false}, expect.anything());
        });
    });
});
