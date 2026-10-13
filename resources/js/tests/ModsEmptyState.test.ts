import {mount} from '@vue/test-utils';
import {describe, expect, it, vi} from 'vitest';
import AdminMods from '@/pages/Admin/Mods.vue';
import PartyMods from '@/pages/Parties/Mods.vue';

vi.mock('@inertiajs/vue3', async () => {
    const {reactive} = await import('vue');

    return {
        Head: {render: () => null},
        useForm: (data: Record<string, unknown>) =>
            reactive({...data, errors: {}, processing: false, transform: () => ({put: vi.fn()})}),
    };
});

vi.mock('@/layouts/AppLayout.vue', () => ({
    default: {template: '<div><slot name="breadcrumbs" /><slot /></div>'},
}));

const party = {code: 'abc', name: 'Test Party'};
const settings = [
    {
        code: 'token',
        name: 'Token',
        description: '',
        type: 'string',
        value: null,
        has_value: false,
        default: null,
        required: false,
    },
];
const partyMod = {code: 'alpha', name: 'Alpha', description: 'First mod', enabled: false, settings};
const adminMod = {code: 'alpha', name: 'Alpha', description: 'First mod', settings};

describe.each([
    ['Parties/Mods', PartyMods, {party}, partyMod],
    ['Admin/Mods', AdminMods, {}, adminMod],
])('%s', (_name, component, extra, mod) => {
    it('shows the empty state and no Save button when there are no mods', () => {
        const wrapper = mount(component, {props: {...extra, mods: []} as never});

        expect(wrapper.find('[data-test="empty-state"]').text()).toContain('No mods available');
        expect(wrapper.find('[data-test="save"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="mod"]').exists()).toBe(false);
    });

    it('lists mods and hides the empty state when mods exist', () => {
        const wrapper = mount(component, {props: {...extra, mods: [mod]} as never});

        expect(wrapper.find('[data-test="empty-state"]').exists()).toBe(false);
        expect(wrapper.findAll('[data-test="mod"]')).toHaveLength(1);
        expect(wrapper.find('[data-test="save"]').exists()).toBe(true);
    });
});
