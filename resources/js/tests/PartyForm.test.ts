import {mount} from '@vue/test-utils';
import {describe, expect, it, vi} from 'vitest';
import {reactive} from 'vue';
import PartyForm from '@/components/PartyForm.vue';
import {initialPartyForm} from '@/lib/partyForm';
import type {PartyFormState, PartyFormValues} from '@/lib/partyForm';
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
const playlists = [{id: 'pl1', name: 'Chill'}];
const common = {
    playlists,
    devices: [{id: 'd1', name: 'Speaker'}],
    defaultCountry: 'GB',
    instanceTimezone: 'UTC',
    timezones: ['UTC', 'Asia/Tokyo'],
};

describe('party form', () => {
    it('maps an unlisted backup playlist to the custom field', () => {
        const values = initialPartyForm(party, playlists, 'GB');

        expect(values.backup_playlist_id).toBe('other');
        expect(values.custom_backup_playlist_id).toBe('custom-id');
        expect(initialPartyForm({...party, backup_playlist_id: 'pl1'}, playlists, 'GB').backup_playlist_id).toBe('pl1');
    });

    it('creates with the v2 field names and shows validation errors', async () => {
        inertia.submitted.length = 0;
        inertia.errors = {name: 'The name field is required.'};
        const wrapper = mount(Create, {
            props: {...common, party: {...party, name: null}, providerIds: ['spotify', 'fake']},
        });

        expect(wrapper.find('.invalid-feedback').text()).toContain('name field is required');
        expect(wrapper.find('select[name="provider_id"]').exists()).toBe(true);
        expect(wrapper.find('[name="context_playlist_id"]').exists()).toBe(false);
        for (const name of ['name', 'country', 'timezone', 'backup_playlist_id', 'device_id', 'max_requests']) {
            expect(wrapper.find(`[name="${name}"]`).exists(), name).toBe(true);
        }

        await wrapper.find('form').trigger('submit');
        expect(inertia.submitted[0].method).toBe('post');
        expect(inertia.submitted[0].url).toBe('/parties');
        expect(inertia.submitted[0].data).toMatchObject({provider_id: 'spotify', backup_playlist_id: 'other'});
        inertia.errors = {};
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

    it('renders as a standalone form component', () => {
        const form = reactive({...initialPartyForm(party, playlists, 'GB'), errors: {}, processing: false});
        const wrapper = mount(PartyForm, {
            props: {form: form as unknown as PartyFormState, ...common, cancelUrl: '/x', providerName: 'Spotify'},
        });

        expect(wrapper.find('input[disabled]').exists()).toBe(true);
    });
});
