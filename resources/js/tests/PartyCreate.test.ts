import {mount} from '@vue/test-utils';
import {describe, expect, it, vi} from 'vitest';
import {reactive} from 'vue';
import {initialCreateForm, initialPartyForm} from '@/lib/partyForm';
import type {PartyFormValues} from '@/lib/partyForm';
import Create from '@/pages/Parties/Create.vue';
import Edit from '@/pages/Parties/Edit.vue';

const inertia = vi.hoisted(() => ({
    submitted: [] as {method: string; url: string; data: Record<string, unknown>}[],
    errors: {} as Record<string, string>,
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: {render: () => null},
    router: {put: vi.fn()},
    Link: {props: ['href'], template: '<a :href="href"><slot /></a>'},
    useForm: (initial: Record<string, unknown>) => {
        const form = reactive({...initial, errors: inertia.errors, processing: false}) as Record<string, unknown>;
        for (const method of ['post', 'patch']) {
            form[method] = (url: string) => {
                const data = Object.fromEntries(Object.keys(initial).map((key) => [key, form[key]]));
                inertia.submitted.push({method, url, data});
            };
        }
        return form;
    },
}));

vi.mock('@/layouts/AppLayout.vue', () => ({
    default: {template: '<div><slot name="breadcrumbs" /><slot name="header" /><slot /></div>'},
}));
vi.mock('@/components/PartyPlayer.vue', () => ({default: {template: '<div class="player" />'}}));
vi.mock('@/routes', () => ({home: () => ({url: '/'})}));
vi.mock('@/routes/parties', () => ({
    create: () => ({url: '/parties/create'}),
    store: () => ({url: '/parties'}),
    dashboard: (code: string) => ({url: `/parties/${code}/dashboard`}),
    tv: (code: string) => ({url: `/parties/${code}/tv`}),
    edit: (code: string) => ({url: `/parties/${code}/edit`}),
    show: (code: string) => ({url: `/parties/${code}`}),
    update: (code: string) => ({url: `/parties/${code}`}),
}));

const party: PartyFormValues = {
    name: 'Disco',
    country: 'GB',
    timezone: null,
    backup_playlist_id: 'custom-id',
    history_playlist_id: null,
    device_id: null,
    active: true,
    allow_requests: true,
    downvotes: false,
    weighted: false,
    show_qrcode: false,
    flag_requests: false,
    explicit: false,
    poll: false,
    force: false,
    min_song_length: null,
    max_song_length: null,
    no_repeat_interval: null,
    downvotes_per_hour: null,
    max_requests: 2,
};
const playlists = [
    {id: 'pl1', name: 'Chill'},
    {id: 'pl2', name: 'Throwback'},
];
const common = {
    playlists,
    devices: [{id: 'd1', name: 'Speaker'}],
    defaultCountry: 'GB',
    instanceTimezone: 'UTC',
    timezones: ['UTC', 'Asia/Tokyo'],
};

describe('create party wizard and edit form', () => {
    it('maps an unlisted backup playlist to the custom field', () => {
        const values = initialPartyForm(party, playlists, 'GB');

        expect(values.backup_playlist_id).toBe('other');
        expect(values.custom_backup_playlist_id).toBe('custom-id');
        expect(initialPartyForm({...party, backup_playlist_id: 'pl1'}, playlists, 'GB').backup_playlist_id).toBe('pl1');
    });

    it('defaults the create fallback playlist to the first of the host playlists', () => {
        const values = initialCreateForm({...party, backup_playlist_id: null}, playlists, 'GB');

        expect(values.backup_playlist_id).toBe('pl1');
        expect(values).not.toHaveProperty('custom_backup_playlist_id');
        expect(initialCreateForm({...party, backup_playlist_id: 'pl2'}, playlists, 'GB').backup_playlist_id).toBe(
            'pl2',
        );
        expect(initialCreateForm({...party, backup_playlist_id: null}, [], 'GB').backup_playlist_id).toBe('');
    });

    it('walks the three steps and requires only a name', async () => {
        inertia.submitted.length = 0;
        const wrapper = mount(Create, {
            props: {...common, party: {...party, name: null}, providerIds: ['spotify', 'fake']},
        });

        expect(wrapper.find('[aria-current="step"]').text()).toBe('Name');
        expect(wrapper.find('select[name="provider_id"]').exists()).toBe(true);
        await wrapper.find('form').trigger('submit');
        expect(wrapper.find('[data-test="setting-error"]').text()).toContain('Enter a name');
        expect(inertia.submitted).toHaveLength(0);

        await wrapper.find('#name').setValue('Disco');
        await wrapper.find('form').trigger('submit');
        expect(wrapper.find('[aria-current="step"]').text()).toBe('Music');
        expect(wrapper.find('[data-test="back"]').exists()).toBe(true);

        await wrapper.find('#backup_playlist_id').trigger('click');
        const options = wrapper.findAll('[data-test="playlist-option"]');
        expect(options.map((o) => o.text())).toEqual(['Chill', 'Throwback']);
        await options[1]!.trigger('click');
        await wrapper.find('form').trigger('submit');
        expect(wrapper.find('[aria-current="step"]').text()).toBe('Defaults');
        for (const name of ['country', 'timezone', 'max_requests', 'show_qrcode']) {
            expect(wrapper.find(`[name="${name}"]`).exists(), name).toBe(true);
        }

        await wrapper.find('[data-test="back"]').trigger('click');
        expect(wrapper.find('[aria-current="step"]').text()).toBe('Music');
        await wrapper.find('form').trigger('submit');
        await wrapper.find('form').trigger('submit');
        expect(inertia.submitted[0]).toMatchObject({method: 'post', url: '/parties'});
        expect(inertia.submitted[0]!.data).toMatchObject({
            provider_id: 'spotify',
            name: 'Disco',
            backup_playlist_id: 'pl2',
        });
        expect(inertia.submitted[0]!.data).not.toHaveProperty('custom_backup_playlist_id');
    });

    it('creates with defaults from the first step', async () => {
        inertia.submitted.length = 0;
        const wrapper = mount(Create, {props: {...common, party, providerIds: ['spotify']}});

        await wrapper.find('[data-test="create-defaults"]').trigger('click');
        expect(inertia.submitted).toHaveLength(1);
    });

    it('lands on the step that holds the earliest errored field', () => {
        inertia.errors = {max_requests: 'Too many.', backup_playlist_id: 'Pick a playlist.'};
        const wrapper = mount(Create, {props: {...common, party, providerIds: ['spotify']}});

        expect(wrapper.find('[aria-current="step"]').text()).toBe('Music');
        expect(wrapper.find('[data-test="setting-error"]').text()).toContain('Pick a playlist');

        inertia.errors = {name: 'The name field is required.', max_requests: 'Too many.'};
        const named = mount(Create, {props: {...common, party, providerIds: ['spotify']}});
        expect(named.find('[aria-current="step"]').text()).toBe('Name');
        expect(named.find('.invalid-feedback').text()).toContain('name field is required');

        inertia.errors = {max_requests: 'Too many.'};
        const defaults = mount(Create, {props: {...common, party, providerIds: ['spotify']}});
        expect(defaults.find('[aria-current="step"]').text()).toBe('Defaults');
        expect(defaults.find('[data-test="setting-error"]').text()).toContain('Too many');
        inertia.errors = {};
    });

    it('shows the done screen for a created party', () => {
        const wrapper = mount(Create, {
            props: {
                ...common,
                party,
                providerIds: ['spotify'],
                createdParty: {
                    code: 'KXQB',
                    name: 'Coyote LAN',
                    joinUrl: 'https://x.test/parties/KXQB',
                    qr: '<svg id="qr"></svg>',
                },
            },
        });

        expect(wrapper.find('[data-test="done-code"]').text()).toBe('KXQB');
        expect(wrapper.find('[data-test="done-code"]').attributes('aria-label')).toBe('K X Q B');
        expect(wrapper.find('[data-test="done-qr"] svg#qr').exists()).toBe(true);
        expect(wrapper.find('[data-test="success-mark"]').exists()).toBe(true);
        expect(wrapper.find('form').exists()).toBe(false);
        const hrefs = wrapper.findAll('[data-test="done"] a').map((a) => a.attributes('href'));
        expect(hrefs).toEqual(['/parties/KXQB/dashboard', '/parties/KXQB/tv', '/parties/KXQB/edit']);
    });

    it('toggles the custom playlist input and patches on edit', async () => {
        inertia.submitted.length = 0;
        const wrapper = mount(Edit, {
            props: {
                ...common,
                party: {...party, code: 'abc'},
                providerName: 'Spotify',
                playerState: {} as never,
                encore: {host: 'x.test', key: 'k', channel: 'private-encore.abc', secret: null},
            },
        });

        await wrapper.find('[data-test="tab-playback"]').trigger('click');
        expect(wrapper.find('#custom_backup_playlist_id').exists()).toBe(true);
        await wrapper.find('#backup_playlist_id').setValue('pl1');
        expect(wrapper.find('#custom_backup_playlist_id').exists()).toBe(false);
        expect(wrapper.find('select[name="provider_id"]').exists()).toBe(false);
        await wrapper.find('[data-test="tab-screens"]').trigger('click');
        expect(wrapper.text()).toContain('private-encore.abc');
        expect(wrapper.text()).not.toContain('App secret');

        await wrapper.find('form').trigger('submit');
        expect(inertia.submitted[0]).toMatchObject({method: 'patch', url: '/parties/abc'});
        expect(inertia.submitted[0].data).not.toHaveProperty('provider_id');
    });
});
