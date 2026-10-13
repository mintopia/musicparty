import {mount} from '@vue/test-utils';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import AdminMods from '@/pages/Admin/Mods.vue';
import PartyMods from '@/pages/Parties/Mods.vue';
import type {ModSetting} from '@/types';

const inertia = vi.hoisted(() => ({put: vi.fn(), errors: {} as Record<string, string>}));

vi.mock('@inertiajs/vue3', async () => {
    const {reactive} = await import('vue');
    return {
        Head: {render: () => null},
        useForm: (initial: Record<string, unknown>) => {
            const form = reactive({
                ...structuredClone(initial),
                errors: inertia.errors,
                processing: false,
                _t: null as null | ((d: never) => unknown),
                transform(cb: (d: never) => unknown) {
                    form._t = cb;
                    return form;
                },
                put(url: string, options?: unknown) {
                    const data = {mods: (form as unknown as {mods: unknown}).mods};
                    inertia.put(url, form._t ? form._t(data as never) : data, options);
                },
            });
            return form;
        },
    };
});

vi.mock('@/layouts/AppLayout.vue', () => ({
    default: {template: '<div><slot name="breadcrumbs" /><slot /></div>'},
}));

function setting(code: string, type: ModSetting['type'], extra: Partial<ModSetting> = {}): ModSetting {
    return {
        code,
        name: code,
        description: '',
        type,
        value: null,
        has_value: false,
        default: null,
        required: false,
        ...extra,
    };
}

const settings = [
    setting('s', 'string', {value: 'hi', has_value: true}),
    setting('b', 'boolean', {value: true, has_value: true}),
    setting('i', 'integer', {value: 3, has_value: true}),
    setting('f', 'float', {default: 1.5}),
    setting('d', 'datetime'),
    setting('t', 'track', {value: 'abc', has_value: true}),
    setting('tod', 'time_of_day', {value: '09:30', has_value: true}),
    setting('sec', 'secret', {value: 'LEAKED', has_value: true}),
];

const party = {code: 'abc', name: 'Party'};

describe('Mods pages', () => {
    beforeEach(() => {
        inertia.put.mockClear();
        inertia.errors = {};
    });

    it('renders an input per setting type and never prefills secrets', () => {
        const w = mount(PartyMods, {
            props: {party, mods: [{code: 'm', name: 'M', description: '', enabled: true, settings}]},
        });
        const input = (code: string) => w.find(`[data-setting="${code}"] input`);
        expect(input('s').attributes('type')).toBe('text');
        expect(input('b').attributes('type')).toBe('checkbox');
        expect((input('b').element as HTMLInputElement).checked).toBe(true);
        expect(input('i').attributes('type')).toBe('number');
        expect((input('f').element as HTMLInputElement).value).toBe('1.5');
        expect(input('t').element.getAttribute('type')).toBe('text');
        expect(input('tod').attributes('type')).toBe('time');
        expect(input('sec').attributes('type')).toBe('password');
        expect((input('sec').element as HTMLInputElement).value).toBe('');
        expect(w.find('[data-setting="sec"] [data-test="secret-hint"]').text()).toContain('set');
        expect(w.html()).not.toContain('LEAKED');
    });

    it('sends null for untouched secrets, the typed value otherwise, and the enable toggle', async () => {
        const w = mount(PartyMods, {
            props: {party, mods: [{code: 'm', name: 'M', description: '', enabled: true, settings}]},
        });
        await w.find('form').trigger('submit');
        const first = inertia.put.mock.calls[0];
        expect(first[0]).toBe('/parties/abc/mods');
        expect(first[1].mods.m.enabled).toBe(true);
        expect(first[1].mods.m.settings).toMatchObject({
            s: 'hi',
            b: true,
            i: 3,
            f: 1.5,
            d: null,
            t: 'abc',
            tod: '09:30',
            sec: null,
        });

        await w.find('[data-test="mod-enabled"]').setValue(false);
        await w.find('[data-setting="sec"] input').setValue('hunter2');
        await w.find('[data-setting="i"] input').setValue('7');
        await w.find('form').trigger('submit');
        const second = inertia.put.mock.calls[1][1];
        expect(second.mods.m.enabled).toBe(false);
        expect(second.mods.m.settings.sec).toBe('hunter2');
        expect(second.mods.m.settings.i).toBe(7);
    });

    it('shows validation errors keyed by mod and setting', () => {
        inertia.errors = {'mods.m.settings.s': 'Bad value'};
        const w = mount(PartyMods, {
            props: {party, mods: [{code: 'm', name: 'M', description: '', enabled: false, settings}]},
        });
        expect(w.find('[data-setting="s"] [data-test="setting-error"]').text()).toBe('Bad value');
    });

    it('admin page puts settings only', async () => {
        const w = mount(AdminMods, {props: {mods: [{code: 'm', name: 'M', description: '', settings}]}});
        await w.find('[data-setting="sec"] input').setValue('k');
        await w.find('form').trigger('submit');
        const [url, body] = inertia.put.mock.calls[0];
        expect(url).toBe('/admin/mods');
        expect(body.mods.m.enabled).toBeUndefined();
        expect(body.mods.m.settings.sec).toBe('k');
        expect(w.find('[data-test="mod-enabled"]').exists()).toBe(false);
    });
});
