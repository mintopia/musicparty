import {enableAutoUnmount, mount} from '@vue/test-utils';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';

const router = vi.hoisted(() => ({delete: vi.fn(), put: vi.fn()}));
const formPut = vi.hoisted(() => vi.fn());

vi.mock('@inertiajs/vue3', async () => {
    const {reactive} = await import('vue');

    return {
        Head: {render: () => null},
        Link: {props: ['href'], template: '<a :href="href"><slot /></a>'},
        router,
        usePage: () => ({props: {}}),
        useForm: (data) => {
            const initial = {...data};
            const form = reactive({
                ...data,
                errors: {},
                processing: false,
                clearErrors: () => {},
                reset: (...keys) => {
                    (keys.length ? keys : Object.keys(initial)).forEach((key) => { form[key] = initial[key]; });
                },
                post: vi.fn(),
                put: formPut,
            });

            return form;
        },
    };
});

vi.mock('../composables/usePartyPresence', () => ({usePartyPresence: () => {}}));

import Blocklist from '../pages/Party/Blocklist.vue';

enableAutoUnmount(afterEach);

const props = (overrides = {}) => ({
    party: {code: 'FRI1', name: 'Friday'},
    entries: [{id: 7, match_type: 'track_name', value: 'Old', is_regex: false, is_enabled: true, notes: null}],
    matchTypes: [
        {value: 'track_name', label: 'Track name'},
        {value: 'isrc', label: 'ISRC'},
    ],
    abilities: {canManage: true},
    ...overrides,
});

beforeEach(() => {
    vi.clearAllMocks();
});

describe('Blocklist page', () => {
    it('asks for confirmation before deleting and does nothing when declined', async () => {
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);
        const w = mount(Blocklist, {props: props()});

        await w.get('[data-testid=blocklist-delete-7]').trigger('click');

        expect(confirm).toHaveBeenCalledOnce();
        expect(router.delete).not.toHaveBeenCalled();

        confirm.mockReturnValue(true);
        await w.get('[data-testid=blocklist-delete-7]').trigger('click');

        expect(router.delete).toHaveBeenCalledWith('/parties/FRI1/blocklist/7', expect.any(Object));
    });

    it('prefills the form from the query-derived prefill prop', () => {
        const w = mount(Blocklist, {props: props({prefill: {match_type: 'isrc', value: 'USRC1', notes: 'Song'}})});

        expect(w.get('[data-testid=blocklist-type-select]').element.value).toBe('isrc');
        expect(w.get('[data-testid=blocklist-value-input]').element.value).toBe('USRC1');
        expect(w.get('[data-testid=blocklist-notes-input]').element.value).toBe('Song');
    });

    it('offers regex on every match type and edits an entry in place', async () => {
        const w = mount(Blocklist, {props: props()});
        await w.get('[data-testid=blocklist-type-select]').setValue('isrc');
        expect(w.find('[data-testid=blocklist-regex-checkbox]').exists()).toBe(true);

        await w.get('[data-testid=blocklist-edit-7]').trigger('click');
        expect(w.get('[data-testid=blocklist-value-input]').element.value).toBe('Old');

        await w.get('[data-testid=blocklist-value-input]').setValue('New');
        await w.get('[data-testid=blocklist-add-form]').trigger('submit');

        expect(formPut).toHaveBeenCalledWith('/parties/FRI1/blocklist/7', expect.any(Object));
    });
});
