import {mount} from '@vue/test-utils';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import AdminMods from '@/pages/Admin/Mods.vue';
import PartyMods from '@/pages/Parties/Mods.vue';
import ModSettingField from '@/components/ModSettingField.vue';
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
                    const data = {
                        enabled: (form as unknown as {enabled: boolean}).enabled,
                        settings: (form as unknown as {settings: unknown}).settings,
                    };
                    inertia.put(url, form._t ? form._t(data as never) : data, options);
                },
            });
            return form;
        },
    };
});

vi.mock('@/layouts/AppLayout.vue', () => ({
    default: {template: '<div><slot name="breadcrumbs" /><slot name="header" /><slot /></div>'},
}));

vi.mock('@/components/PartyPlayer.vue', () => ({default: {template: '<div data-test="player" />'}}));

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
const playerState = {} as never;

describe('Mods pages', () => {
    beforeEach(() => {
        inertia.put.mockClear();
        inertia.errors = {};
    });

    it('renders an input per setting type and never prefills secrets', () => {
        const w = mount(PartyMods, {
            props: {party, playerState, mods: [{code: 'm', name: 'M', description: '', enabled: true, settings}]},
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
            props: {party, playerState, mods: [{code: 'm', name: 'M', description: '', enabled: true, settings}]},
        });
        await w.find('form').trigger('submit');
        const first = inertia.put.mock.calls[0];
        expect(first[0]).toBe('/parties/abc/mods/m');
        expect(first[1].enabled).toBe(true);
        expect(first[1].settings).toMatchObject({
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
        expect(second.enabled).toBe(false);
        expect(second.settings.sec).toBe('hunter2');
        expect(second.settings.i).toBe(7);
    });

    it('warns on the AI review mod only when it is enabled and flagged requests are not held', async () => {
        const mods = [
            {code: 'ai-request-review', name: 'AI', description: '', enabled: true, settings: []},
            {code: 'other', name: 'Other', description: '', enabled: true, settings: []},
        ];
        const off = mount(PartyMods, {
            props: {party, playerState, mods, holdsFlagged: false, settingsUrl: '/parties/abc/edit#flag_requests'},
        });
        const notices = off.findAll('[data-test="mod-notice"]');
        expect(notices).toHaveLength(1);
        expect(notices[0]?.text()).toContain("Flagged requests aren't held");
        expect(notices[0]?.find('a').attributes('href')).toBe('/parties/abc/edit#flag_requests');

        await off.findAll('[data-test="mod-enabled"]')[0]?.setValue(false);
        expect(off.find('[data-test="mod-notice"]').exists()).toBe(false);

        const on = mount(PartyMods, {props: {party, playerState, mods, holdsFlagged: true}});
        expect(on.find('[data-test="mod-notice"]').exists()).toBe(false);
    });

    it('renders choice settings as radios and submits the chosen key', async () => {
        const driver = setting('driver', 'choice', {
            value: 'decision-model',
            has_value: true,
            options: {'decision-model': 'Decision model (Jev / TypeSafe)', 'openai-compatible': 'OpenAI-compatible'},
        });
        const w = mount(PartyMods, {
            props: {
                party,
                playerState,
                mods: [{code: 'm', name: 'M', description: '', enabled: true, settings: [driver]}],
            },
        });
        const radios = w.findAll('[data-setting="driver"] input[type="radio"]');
        expect(radios).toHaveLength(2);
        expect((radios[0].element as HTMLInputElement).checked).toBe(true);
        expect(w.find('[data-setting="driver"]').text()).toContain('OpenAI-compatible');
        await radios[1].setValue(true);
        await w.find('form').trigger('submit');
        expect(inertia.put.mock.calls[0][1].settings.driver).toBe('openai-compatible');
    });

    it('ModSettingField emits the option key on selection', async () => {
        const field = setting('driver', 'choice', {options: {a: 'A', b: 'B'}});
        const w = mount(ModSettingField, {props: {setting: field, modelValue: 'b', id: 'x'}});
        const radios = w.findAll('[data-test="setting-input"]');
        expect(radios.map((r) => (r.element as HTMLInputElement).checked)).toEqual([false, true]);
        await radios[0].setValue(true);
        expect(w.emitted('update:modelValue')?.[0]).toEqual(['a']);
    });

    it('ModSettingField renders text settings as a multi-line textarea and keeps newlines', async () => {
        const field = setting('guidelines', 'text');
        const w = mount(ModSettingField, {props: {setting: field, modelValue: 'a\nb', id: 'x'}});
        const area = w.find('textarea[data-test="setting-input"]');
        expect(area.attributes('rows')).toBe('5');
        expect((area.element as HTMLTextAreaElement).value).toBe('a\nb');
        await area.setValue('no explicit\nno live versions');
        expect(w.emitted('update:modelValue')?.[0]).toEqual(['no explicit\nno live versions']);
    });

    it('shows validation errors keyed by mod and setting', () => {
        inertia.errors = {'mods.m.settings.s': 'Bad value'};
        const w = mount(PartyMods, {
            props: {party, playerState, mods: [{code: 'm', name: 'M', description: '', enabled: false, settings}]},
        });
        expect(w.find('[data-setting="s"] [data-test="setting-error"]').text()).toBe('Bad value');
    });

    it('admin page puts settings only', async () => {
        const w = mount(AdminMods, {props: {mods: [{code: 'm', name: 'M', description: '', settings}]}});
        await w.find('[data-setting="sec"] input').setValue('k');
        await w.find('form').trigger('submit');
        const [url, body] = inertia.put.mock.calls[0];
        expect(url).toBe('/admin/mods/m');
        expect(body.enabled).toBeUndefined();
        expect(body.settings.sec).toBe('k');
        expect(w.find('[data-test="mod-enabled"]').exists()).toBe(false);
    });

    it('starts enabled mods expanded, disabled mods collapsed, and expands on enable', async () => {
        const w = mount(PartyMods, {
            props: {
                party,
                playerState,
                mods: [
                    {code: 'on', name: 'On', description: '', enabled: true, settings: [setting('s', 'string')]},
                    {code: 'off', name: 'Off', description: '', enabled: false, settings: [setting('s', 'string')]},
                ],
            },
        });
        const body = (code: string) => w.find(`[data-mod="${code}"] .accordion-collapse`);
        expect(body('on').classes()).toContain('show');
        expect(body('off').classes()).not.toContain('show');
        await w.find('[data-mod="off"] [data-test="mod-enabled"]').setValue(true);
        expect(body('off').classes()).toContain('show');
    });

    it('keeps the description out of the heading and labels the enable switch without toggling the accordion', async () => {
        const w = mount(PartyMods, {
            props: {
                party,
                playerState,
                mods: [{code: 'off', name: 'Off', description: 'Does things', enabled: false, settings: []}],
            },
        });
        const mod = w.find('[data-mod="off"]');
        const toggle = mod.find('[data-test="mod-toggle"]');
        expect(mod.find('h2').text()).toBe('Off');
        expect(mod.find('[data-test="mod-description"]').text()).toBe('Does things');
        expect(toggle.attributes('aria-describedby')).toBe('mod-off-description');
        expect(toggle.attributes('aria-expanded')).toBe('false');
        expect(mod.find('[data-test="mod-enabled-label"]').text()).toBe('Off');

        await mod.find('[data-test="mod-enabled"]').setValue(true);
        expect(mod.find('[data-test="mod-enabled-label"]').text()).toBe('Enabled');
        expect(toggle.attributes('aria-expanded')).toBe('true');

        await mod.find('[data-test="mod-enabled"]').setValue(false);
        expect(toggle.attributes('aria-expanded')).toBe('true');
        await toggle.trigger('click');
        expect(toggle.attributes('aria-expanded')).toBe('false');
    });

    it('submits only the saved mod', async () => {
        const w = mount(PartyMods, {
            props: {
                party,
                playerState,
                mods: [
                    {code: 'a', name: 'A', description: '', enabled: true, settings: [setting('s', 'string')]},
                    {code: 'b', name: 'B', description: '', enabled: true, settings: [setting('s', 'string')]},
                ],
            },
        });
        await w.find('[data-mod="b"] form').trigger('submit');
        expect(inertia.put).toHaveBeenCalledTimes(1);
        expect(inertia.put.mock.calls[0][0]).toBe('/parties/abc/mods/b');
    });
});
