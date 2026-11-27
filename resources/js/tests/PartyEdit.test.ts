import {flushPromises, mount} from '@vue/test-utils';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import CopyButton from '@/components/CopyButton.vue';
import Edit from '@/pages/Parties/Edit.vue';
import type {ModSetting} from '@/types';

const inertia = vi.hoisted(() => ({
    patch: vi.fn(),
    put: vi.fn(),
    patchOutcome: 'success' as 'success' | 'error',
    putErrors: {} as Record<string, Record<string, string>>,
}));

vi.mock('@inertiajs/vue3', async () => {
    const {reactive} = await import('vue');

    return {
        Head: {render: () => null},
        router: {
            put: (
                url: string,
                data: unknown,
                options: {onSuccess: () => void; onError: (errors: Record<string, string>) => void},
            ) => {
                inertia.put(url, data);
                const code = url.split('/').pop() as string;
                const errors = inertia.putErrors[code];
                if (errors) {
                    options.onError(errors);
                } else {
                    options.onSuccess();
                }
            },
        },
        useForm: (data: Record<string, unknown>) => {
            const form = reactive({
                ...data,
                errors: {} as Record<string, string>,
                processing: false,
                clearErrors() {
                    form.errors = {};
                },
                patch(
                    url: string,
                    options: {
                        onSuccess: () => void;
                        onError: () => void;
                    },
                ) {
                    inertia.patch(url);
                    if (inertia.patchOutcome === 'success') {
                        options.onSuccess();
                    } else {
                        form.errors = {name: 'Required'};
                        options.onError();
                    }
                },
            });
            return form;
        },
    };
});
vi.mock('@/layouts/AppLayout.vue', () => ({default: {template: '<div><slot /></div>'}}));
vi.mock('@/components/PartyPlayer.vue', () => ({default: {template: '<div />'}}));

const party = {
    code: 'abc',
    name: 'P',
    country: 'GB',
    timezone: null,
    backup_playlist_id: 'pl1',
    history_playlist_id: null,
    device_id: null,
    active: true,
    allow_requests: true,
    downvotes: true,
    weighted: false,
    show_qrcode: false,
    flag_requests: true,
    explicit: false,
    poll: false,
    force: false,
    min_song_length: null,
    max_song_length: null,
    no_repeat_interval: null,
    downvotes_per_hour: null,
    max_requests: null,
};

function setting(code: string, type: ModSetting['type'], value: unknown = null): ModSetting {
    return {code, name: code, description: '', type, value, has_value: value !== null, default: null, required: false};
}

const mods = [
    {code: 'alpha', name: 'Alpha', description: 'First', enabled: true, settings: [setting('token', 'string', 'a')]},
    {code: 'beta', name: 'Beta', description: 'Second', enabled: true, settings: [setting('token', 'string', 'b')]},
];

function render(extra: Record<string, unknown> = {}) {
    return mount(Edit, {
        props: {
            party,
            providerName: 'Spotify',
            playlists: [{id: 'pl1', name: 'Chill'}],
            devices: [],
            defaultCountry: 'AU',
            instanceTimezone: 'UTC',
            timezones: [],
            playerState: {},
            encore: {host: 'mp.test', key: 'k1', channel: 'party.abc', secret: 's3cret'},
            mods,
            ...extra,
        } as never,
    });
}

beforeEach(() => {
    inertia.patch.mockClear();
    inertia.put.mockClear();
    inertia.patchOutcome = 'success';
    inertia.putErrors = {};
    window.history.replaceState({}, '', '/parties/abc/edit');
});

describe('Parties/Edit tabs', () => {
    it('starts on the tab named by the prop and switches without leaving the page', async () => {
        const w = render({tab: 'playback'});
        expect(w.find('[data-test="tab-playback"]').attributes('aria-selected')).toBe('true');
        expect(w.find('#backup_playlist_id').exists()).toBe(true);

        await w.find('[data-test="tab-requests"]').trigger('click');
        expect(w.find('#max_requests').exists()).toBe(true);
        expect(w.find('#backup_playlist_id').exists()).toBe(false);
        expect(window.location.search).toBe('?tab=requests');
    });

    it('defaults to General and lists the five tabs', () => {
        const w = render();
        expect(w.findAll('[role="tab"]').map((t) => t.text())).toEqual([
            'General',
            'Requests',
            'Playback',
            'Screens',
            'Mods',
        ]);
        expect(w.find('#name').exists()).toBe(true);
    });

    it('opens the Requests tab when linked to #flag_requests', async () => {
        window.history.replaceState({}, '', '/parties/abc/edit#flag_requests');
        const w = render();
        await flushPromises();
        expect(w.find('[data-test="tab-requests"]').attributes('aria-selected')).toBe('true');
        expect(w.find('#flag_requests').exists()).toBe(true);
    });

    it('offers a copy button for each Encore relay value on Screens', () => {
        const buttons = render({tab: 'screens'}).findAllComponents(CopyButton);

        expect(buttons.map((b) => [b.props('label'), b.props('value')])).toEqual([
            ['Copy relay URL', 'wss://mp.test/app/k1'],
            ['Copy channel', 'party.abc'],
            ['Copy app key', 'k1'],
            ['Copy app secret', 's3cret'],
        ]);
    });

    it('has no secret button when the secret is not provided', () => {
        const labels = render({tab: 'screens', encore: {host: 'mp.test', key: 'k1', channel: 'c', secret: null}})
            .findAllComponents(CopyButton)
            .map((b) => b.props('label'));

        expect(labels).not.toContain('Copy app secret');
        expect(labels).toHaveLength(3);
    });
});

describe('Parties/Edit save bar', () => {
    it('appears only when something changed and discards back to the saved values', async () => {
        const w = render();
        expect(w.find('[data-test="save-bar"]').exists()).toBe(false);

        await w.find('#name').setValue('Renamed');
        expect(w.find('[data-test="save-summary"]').text()).toBe('1 change: General');

        await w.find('[data-test="discard"]').trigger('click');
        expect(w.find('[data-test="save-bar"]').exists()).toBe(false);
        expect((w.find('#name').element as HTMLInputElement).value).toBe('P');
    });

    it('counts a picked fallback playlist and a country as changes, then discards both', async () => {
        const w = render({
            tab: 'playback',
            playlists: [
                {id: 'pl1', name: 'Chill', trackCount: 3},
                {id: 'pl2', name: 'Party', trackCount: 9},
            ],
        });
        await w.find('#backup_playlist_id').trigger('click');
        await w.findAll('[data-test="playlist-option"]')[1]!.trigger('click');
        expect(w.find('[data-test="save-summary"]').text()).toBe('1 change: Playback');

        await w.find('[data-test="discard"]').trigger('click');
        expect(w.find('[data-test="save-bar"]').exists()).toBe(false);
        expect(w.find('#backup_playlist_id').text()).toContain('Chill');

        await w.find('[data-test="tab-general"]').trigger('click');
        await w.find('#country').trigger('click');
        await w.findAll('[data-test="picker-option"]').find((o) => o.text().includes('Canada'))!.trigger('click');
        expect(w.find('[data-test="save-summary"]').text()).toBe('1 change: General');
    });

    it('saves the picked fallback playlist', async () => {
        const w = render({
            tab: 'playback',
            playlists: [
                {id: 'pl1', name: 'Chill'},
                {id: 'pl2', name: 'Party'},
            ],
        });
        await w.find('#backup_playlist_id').trigger('click');
        await w.findAll('[data-test="playlist-option"]')[1]!.trigger('click');
        await w.find('[data-test="settings-form"]').trigger('submit');
        await flushPromises();

        expect(inertia.patch).toHaveBeenCalledTimes(1);
        expect(w.find('[data-test="save-bar"]').exists()).toBe(false);
    });

    it('saves party fields and only the changed mods', async () => {
        const w = render({tab: 'mods'});
        await w.find('#mod-beta-token').setValue('changed');
        expect(w.find('[data-test="save-summary"]').text()).toBe('1 change: Beta');

        await w.find('[data-test="settings-form"]').trigger('submit');
        await flushPromises();

        expect(inertia.patch).not.toHaveBeenCalled();
        expect(inertia.put).toHaveBeenCalledTimes(1);
        expect(inertia.put.mock.calls[0]).toEqual([
            '/parties/abc/mods/beta',
            {enabled: true, settings: {token: 'changed'}},
        ]);
        expect(w.find('[data-test="save-bar"]').exists()).toBe(false);
    });

    it('sends a toggled mod and party fields together on one save', async () => {
        const w = render({tab: 'mods'});
        await w.find('#mod-alpha-enabled').setValue(false);
        await w.find('[data-test="tab-general"]').trigger('click');
        await w.find('#name').setValue('Renamed');
        expect(w.find('[data-test="save-summary"]').text()).toBe('2 changes: General, Alpha');

        await w.find('[data-test="settings-form"]').trigger('submit');
        await flushPromises();

        expect(inertia.patch).toHaveBeenCalledWith('/parties/abc');
        expect(inertia.put.mock.calls[0]?.[1]).toMatchObject({enabled: false});
    });

    it('shows a failing mod error on its row without blocking the other mods', async () => {
        inertia.putErrors = {alpha: {'mods.alpha.settings.token': 'Invalid token'}};
        const w = render({tab: 'mods'});
        await w.find('#mod-alpha-token').setValue('bad');
        await w.find('#mod-beta-token').setValue('good');

        await w.find('[data-test="settings-form"]').trigger('submit');
        await flushPromises();

        expect(inertia.put.mock.calls.map((c) => c[0])).toEqual(['/parties/abc/mods/alpha', '/parties/abc/mods/beta']);
        expect(w.find('#mod-alpha [data-test="setting-error"]').text()).toBe('Invalid token');
        expect(w.find('[data-test="save-summary"]').text()).toBe('1 change: Alpha');
    });

    it('turns on approval from the AI review notice as an unsaved change', async () => {
        const aiMods = [{code: 'ai-request-review', name: 'AI', description: '', enabled: true, settings: []}];
        const w = render({tab: 'mods', mods: aiMods, party: {...party, flag_requests: false}});

        await w.find('[data-test="mod-notice"] button').trigger('click');

        expect(w.find('[data-test="mod-notice"]').exists()).toBe(false);
        expect(w.find('[data-test="save-summary"]').text()).toBe('1 change: Requests');
    });
});
