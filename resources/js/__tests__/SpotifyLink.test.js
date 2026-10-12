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

    it('links to Spotify from settings and create', () => {
        const settings = mount(Settings, {props: {party: {code: 'A1', name: 'P'}, settings: {}, spotify_link_url: '/spotify'}});
        expect(settings.get('[data-testid=playback-section] [data-testid=spotify-link]').attributes('href')).toBe('/spotify');
        const create = mount(Create, {props: {spotify_link_url: '/spotify'}});
        expect(create.get('[data-testid=spotify-link]').attributes('href')).toBe('/spotify');
    });
});
