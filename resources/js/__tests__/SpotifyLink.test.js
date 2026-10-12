import {enableAutoUnmount, mount} from '@vue/test-utils';
import {afterEach, describe, expect, it, vi} from 'vitest';

const destroy = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    Head: {render: () => null},
    Link: {props: ['href'], template: '<a :href="href"><slot /></a>'},
    router: {delete: (...args) => destroy(...args), reload: vi.fn()},
    usePage: () => ({props: {errors: {}}}),
    useForm: (data) => ({...data, errors: {}, processing: false}),
}));

import SpotifyLink from '../pages/Spotify/Link.vue';
import Show from '../pages/Party/Show.vue';
import Settings from '../pages/Party/Settings.vue';
import Create from '../pages/Party/Create.vue';

enableAutoUnmount(afterEach);

const props = (over = {}) => ({
    control: {linked: false, name: null, needs_relink: false},
    can_create_party: false,
    link_url: '/spotify/redirect',
    unlink_url: '/spotify/unlink',
    create_party_url: '/parties/create',
    ...over,
});

describe('Spotify link page', () => {
    it('shows the link button when unlinked and disables Create Party', () => {
        const w = mount(SpotifyLink, {props: props()});
        expect(w.text()).toContain('Link Spotify Account');
        expect(w.get('[data-testid=control-icon]').classes()).toContain('text-muted');
        expect(w.get('[data-testid=link]').attributes('href')).toBe('/spotify/redirect');
        expect(w.find('[data-testid=unlink]').exists()).toBe(false);
        expect(w.get('[data-testid=create-party]').attributes('disabled')).toBeDefined();
    });

    it('shows a green icon with the linked name and enables Create Party', () => {
        const w = mount(SpotifyLink, {props: props({control: {linked: true, name: 'Jess', needs_relink: false}, can_create_party: true})});
        const name = w.get('[data-testid=linked-name]');
        expect(name.text()).toBe('Jess');
        expect(w.get('[data-testid=control-icon]').classes()).toContain('text-green-500');
        const create = w.get('[data-testid=create-party]');
        expect(create.attributes('disabled')).toBeUndefined();
        expect(create.attributes('href')).toBe('/parties/create');
    });

    it('keeps Create Party disabled without permission', () => {
        const w = mount(SpotifyLink, {props: props({control: {linked: true, name: 'Jess', needs_relink: false}})});
        expect(w.get('[data-testid=create-party]').attributes('disabled')).toBeDefined();
    });

    it('asks for a re-link and disables Create Party', () => {
        const w = mount(SpotifyLink, {props: props({control: {linked: true, name: 'Jess', needs_relink: true}, can_create_party: true})});
        expect(w.text()).toContain('Re-link needed for playback');
        expect(w.get('[data-testid=relink]').attributes('href')).toBe('/spotify/redirect');
        expect(w.get('[data-testid=create-party]').attributes('disabled')).toBeDefined();
    });

    it('unlinks only after confirmation', async () => {
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);
        const w = mount(SpotifyLink, {props: props({control: {linked: true, name: 'Jess', needs_relink: false}})});
        await w.get('[data-testid=unlink]').trigger('click');
        expect(destroy).not.toHaveBeenCalled();
        confirm.mockReturnValue(true);
        await w.get('[data-testid=unlink]').trigger('click');
        expect(destroy).toHaveBeenCalledWith('/spotify/unlink');
        confirm.mockRestore();
    });
});

describe('Spotify Search card', () => {
    const search = (over = {}, extra = {}) => mount(SpotifyLink, {props: props({
        search_enabled: true,
        search: {linked: false, name: null, needs_relink: false},
        search_link_url: '/spotify/link/search',
        search_unlink_url: '/spotify/unlink/search',
        ...extra,
        ...over,
    })});

    it('is hidden unless the admin has enabled the provider', () => {
        expect(mount(SpotifyLink, {props: props()}).find('[data-testid=search-card]').exists()).toBe(false);
        expect(mount(SpotifyLink, {props: props({search_enabled: false, search: {linked: false, name: null, needs_relink: false}})}).find('[data-testid=search-card]').exists()).toBe(false);
    });

    it('offers an optional link that never gates Create Party', () => {
        const w = search({can_create_party: true, control: {linked: true, name: 'Jess', needs_relink: false}});
        expect(w.get('[data-testid=search-link]').attributes('href')).toBe('/spotify/link/search');
        expect(w.get('[data-testid=create-party]').attributes('disabled')).toBeUndefined();
    });

    it('shows the linked name and unlinks only after confirmation', async () => {
        destroy.mockClear();
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);
        const w = search({search: {linked: true, name: 'Searcher', needs_relink: false}});
        expect(w.get('[data-testid=search-linked-name]').text()).toBe('Searcher');
        expect(w.get('[data-testid=search-icon]').classes()).toContain('text-green-500');
        await w.get('[data-testid=search-unlink]').trigger('click');
        expect(destroy).not.toHaveBeenCalled();
        confirm.mockReturnValue(true);
        await w.get('[data-testid=search-unlink]').trigger('click');
        expect(destroy).toHaveBeenCalledWith('/spotify/unlink/search');
        confirm.mockRestore();
    });

    it('asks for a re-link when the Search account was rejected', () => {
        const w = search({search: {linked: true, name: 'Searcher', needs_relink: true}});
        expect(w.get('[data-testid=search-relink-notice]').text()).toContain('Re-link needed for search');
        expect(w.get('[data-testid=search-relink]').attributes('href')).toBe('/spotify/link/search');
        expect(w.get('[data-testid=search-icon]').classes()).toContain('text-muted');
    });
});

describe('Spotify links elsewhere', () => {
    const show = (over) => mount(Show, {
        props: {party: {code: 'A1', name: 'P', state: 'live', downvotes: true}, membership: {role: 'member', banned: false}, ...over},
        global: {stubs: {NowPlayingBanner: true, UpNextCard: true, PlayedHistory: true, QueueList: true, SearchPanel: true, PlaybackControls: true}},
    });

    it('shows the playback warning only when a link is needed', () => {
        const warn = show({spotify_link_needed: true, spotify_link_url: '/spotify'});
        expect(warn.get('[data-testid=spotify-link-warning]').text()).toContain('Playback needs a linked Spotify account.');
        expect(warn.get('[data-testid=spotify-link-warning] a').attributes('href')).toBe('/spotify');
        expect(show({}).find('[data-testid=spotify-link-warning]').exists()).toBe(false);
    });

    it('warns the host when the Search account needs re-linking', () => {
        const warn = show({spotify_search_relink_needed: true, spotify_link_url: '/spotify'});
        expect(warn.get('[data-testid=spotify-search-relink-warning]').text()).toContain('Spotify Search account needs re-linking');
        expect(warn.get('[data-testid=spotify-search-relink-warning] a').attributes('href')).toBe('/spotify');
        expect(show({}).find('[data-testid=spotify-search-relink-warning]').exists()).toBe(false);
    });

    it('links to Spotify from settings and create', () => {
        const settings = mount(Settings, {props: {party: {code: 'A1', name: 'P'}, settings: {}, spotify_link_url: '/spotify'}});
        expect(settings.get('[data-testid=playback-section] [data-testid=spotify-link]').attributes('href')).toBe('/spotify');
        const create = mount(Create, {props: {spotify_link_url: '/spotify'}});
        expect(create.get('[data-testid=spotify-link]').attributes('href')).toBe('/spotify');
    });
});
