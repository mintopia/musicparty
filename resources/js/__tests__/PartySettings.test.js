import {describe, expect, it, vi} from 'vitest';
import {mount} from '@vue/test-utils';
import {reactive} from 'vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: {render: () => null},
    Link: {props: ['href'], template: '<a :href="href"><slot /></a>'},
    usePage: () => ({props: {}}),
    useForm: (data) => reactive({
        ...data,
        errors: {},
        processing: false,
        transform() { return this; },
        patch() {},
        put() {},
    }),
}));

vi.mock('../composables/usePartyPresence', () => ({usePartyPresence: () => {}}));

import Settings from '../pages/Party/Settings.vue';

const mountSettings = (settings = {}) => mount(Settings, {
    props: {
        party: {code: 'FRI1', name: 'Friday'},
        mods_url: '/parties/FRI1/mods',
        theme_url: '/parties/FRI1/theme',
        settings: {
            allow_requests: true, max_requests: null, min_song_length: null, max_song_length: null,
            explicit: true, hold_requests: false, no_repeat_interval: null,
            downvotes: true, downvotes_per_hour: 3, selection_mode: 'deterministic', freeze_when_paused: false,
            ...settings,
        },
    },
    global: {stubs: {ModSlot: true}},
});

describe('Party settings page', () => {
    it('shows the downvotes-per-hour field only while downvotes are on', async () => {
        const wrapper = mountSettings();

        expect(wrapper.find('[data-testid="setting-downvotes_per_hour"]').exists()).toBe(true);

        await wrapper.find('[data-testid="setting-downvotes"]').setValue(false);

        expect(wrapper.find('[data-testid="setting-downvotes_per_hour"]').exists()).toBe(false);
    });

    it('explains the raffle and links to the Mods page', () => {
        const wrapper = mountSettings();

        expect(wrapper.find('[data-testid="raffle-hint"]').text()).toBe('Tracks with more upvotes are more likely to be played.');
        expect(wrapper.find('[data-testid="mods-link"]').attributes('href')).toBe('/parties/FRI1/mods');
    });

    it('shows the Display section with the theme link and the QR code toggle only when the setting exists', () => {
        const without = mountSettings();

        expect(without.find('[data-testid="display-section"]').exists()).toBe(true);
        expect(without.find('[data-testid="theme-link"]').attributes('href')).toBe('/parties/FRI1/theme');
        expect(without.find('[data-testid="setting-show_qrcode"]').exists()).toBe(false);

        const withQr = mountSettings({show_qrcode: true});

        expect(withQr.find('[data-testid="setting-show_qrcode"]').exists()).toBe(true);
    });
});
