import {mount} from '@vue/test-utils';
import {describe, expect, it, vi} from 'vitest';
import CopyButton from '@/components/CopyButton.vue';
import Edit from '@/pages/Parties/Edit.vue';

vi.mock('@inertiajs/vue3', async () => {
    const {reactive} = await import('vue');

    return {
        Head: {render: () => null},
        useForm: (data: Record<string, unknown>) => reactive({...data, errors: {}, processing: false}),
    };
});
vi.mock('@/layouts/AppLayout.vue', () => ({default: {template: '<div><slot /></div>'}}));
vi.mock('@/components/PartyForm.vue', () => ({default: {template: '<div />'}}));
vi.mock('@/components/PartyPlayer.vue', () => ({default: {template: '<div />'}}));
vi.mock('@/lib/partyForm', () => ({initialPartyForm: () => ({})}));

function render(secret: string | null) {
    return mount(Edit, {
        props: {
            party: {code: 'abc', name: 'P'},
            providerName: 'Spotify',
            playlists: [],
            devices: [],
            defaultCountry: 'AU',
            instanceTimezone: 'UTC',
            timezones: [],
            playerState: {},
            encore: {host: 'mp.test', key: 'k1', channel: 'party.abc', secret},
        } as never,
    });
}

describe('Parties/Edit Encore relay', () => {
    it('offers a copy button for each value with the raw value', () => {
        const buttons = render('s3cret').findAllComponents(CopyButton);

        expect(buttons.map((b) => [b.props('label'), b.props('value')])).toEqual([
            ['Copy relay URL', 'wss://mp.test/app/k1'],
            ['Copy channel', 'party.abc'],
            ['Copy app key', 'k1'],
            ['Copy app secret', 's3cret'],
        ]);
    });

    it('has no secret button when the secret is not provided', () => {
        const labels = render(null)
            .findAllComponents(CopyButton)
            .map((b) => b.props('label'));

        expect(labels).not.toContain('Copy app secret');
        expect(labels).toHaveLength(3);
    });
});
