import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {enableAutoUnmount, flushPromises, mount} from '@vue/test-utils';

const router = vi.hoisted(() => ({put: vi.fn()}));

vi.mock('@inertiajs/vue3', () => ({
    router,
    Link: {props: ['href'], template: '<a :href="href"><slot /></a>'},
}));

import PartyPlayerSettings from '../Components/PartyPlayerSettings.vue';

enableAutoUnmount(afterEach);

const kinds = [{value: 'polling', label: 'Polling Player'}, {value: 'soloist', label: 'Soloist'}];

const props = (over = {}) => ({
    playerKind: 'polling',
    playerKinds: kinds,
    playbackDevice: {id: 'd2', name: 'Kitchen'},
    playerTokensUrl: '/parties/A1/player-tokens',
    devicesUrl: '/parties/A1/player/devices',
    playerUpdateUrl: '/parties/A1/player',
    spotifyLinkUrl: '/spotify',
    ...over,
});

const respond = (status, body) => vi.fn(() => Promise.resolve({status, ok: status < 400, json: () => Promise.resolve(body)}));

beforeEach(() => {
    router.put.mockReset();
});

describe('Party player settings', () => {
    it('shows loading while devices are fetched', async () => {
        vi.stubGlobal('fetch', vi.fn(() => new Promise(() => {})));
        const w = mount(PartyPlayerSettings, {props: props()});
        await flushPromises();
        expect(w.find('[data-testid=devices-loading]').exists()).toBe(true);
    });

    it('lists devices, preselects the current one and saves a change', async () => {
        vi.stubGlobal('fetch', respond(200, {data: [
            {id: 'd1', name: 'Lounge', type: 'Speaker', is_active: true},
            {id: 'd2', name: 'Kitchen', type: 'Speaker', is_active: false},
        ]}));
        const w = mount(PartyPlayerSettings, {props: props()});
        await flushPromises();
        const select = w.get('[data-testid=device-select]');
        expect(select.element.value).toBe('d2');
        await select.setValue('d1');
        expect(router.put).toHaveBeenCalledWith('/parties/A1/player', {player_kind: 'polling', device_id: 'd1', device_name: 'Lounge'}, expect.anything());
    });

    it('shows an empty message when no devices exist', async () => {
        vi.stubGlobal('fetch', respond(200, {data: []}));
        const w = mount(PartyPlayerSettings, {props: props()});
        await flushPromises();
        expect(w.find('[data-testid=devices-empty]').exists()).toBe(true);
    });

    it('asks to re-link Spotify on a 409', async () => {
        vi.stubGlobal('fetch', respond(409, {relink_required: true}));
        const w = mount(PartyPlayerSettings, {props: props()});
        await flushPromises();
        expect(w.find('[data-testid=device-select]').exists()).toBe(false);
        expect(w.get('[data-testid=relink-link]').attributes('href')).toBe('/spotify');
    });

    it('links to Player Tokens for the Soloist instead of the picker', async () => {
        const fetchMock = respond(200, {data: []});
        vi.stubGlobal('fetch', fetchMock);
        const w = mount(PartyPlayerSettings, {props: props({playerKind: 'soloist', playbackDevice: null})});
        await flushPromises();
        expect(w.get('[data-testid=player-tokens-link]').attributes('href')).toBe('/parties/A1/player-tokens');
        expect(w.find('[data-testid=device-picker]').exists()).toBe(false);
        expect(fetchMock).not.toHaveBeenCalled();
    });

    it('saves the kind when changed', async () => {
        vi.stubGlobal('fetch', respond(200, {data: []}));
        const w = mount(PartyPlayerSettings, {props: props({playerKind: 'soloist'})});
        await w.get('[data-testid=player-kind]').setValue('polling');
        expect(router.put).toHaveBeenCalledWith('/parties/A1/player', {player_kind: 'polling'}, expect.anything());
    });
});
