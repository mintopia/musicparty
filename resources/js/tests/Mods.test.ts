import {mount} from '@vue/test-utils';
import {reactive} from 'vue';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import AdminMods from '@/pages/Admin/Mods.vue';
import PartyModsTab from '@/components/PartyModsTab.vue';
import {initialModDraft} from '@/lib/mods';
import ModSettingField from '@/components/ModSettingField.vue';
import ModCard from '@/components/ModCard.vue';
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

type ModFixture = {code: string; name: string; description: string; enabled: boolean; settings: ModSetting[]};

function mountTab(mods: ModFixture[], extra: Record<string, unknown> = {}) {
    const drafts = reactive(Object.fromEntries(mods.map((m) => [m.code, initialModDraft(m)])));
    const wrapper = mount(PartyModsTab, {
        props: {mods, drafts, errors: {}, userSearchUrl: '/u', holdsFlagged: true, ...extra} as never,
    });
    return {wrapper, drafts};
}

describe('Mods pages', () => {
    beforeEach(() => {
        inertia.put.mockClear();
        inertia.errors = {};
    });

    it('renders an input per setting type and never prefills secrets', () => {
        const {wrapper: w} = mountTab([{code: 'm', name: 'M', description: '', enabled: true, settings}]);
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

    it('warns on the AI review mod only when it is enabled and flagged requests are not held', async () => {
        const mods = [
            {code: 'ai-request-review', name: 'AI', description: '', enabled: true, settings: []},
            {code: 'other', name: 'Other', description: '', enabled: true, settings: []},
        ];
        const {wrapper: off, drafts} = mountTab(mods, {holdsFlagged: false});
        const notices = off.findAll('[data-test="mod-notice"]');
        expect(notices).toHaveLength(1);
        expect(notices[0]?.text()).toContain("Flagged Requests aren't held");

        await notices[0]?.find('button').trigger('click');
        expect(off.emitted('hold-flagged')).toHaveLength(1);

        drafts['ai-request-review']!.enabled = false;
        await off.vm.$nextTick();
        expect(off.find('[data-test="mod-notice"]').exists()).toBe(false);

        const {wrapper: on} = mountTab(mods, {holdsFlagged: true});
        expect(on.find('[data-test="mod-notice"]').exists()).toBe(false);
    });

    it('renders choice settings as radios and updates the draft with the chosen key', async () => {
        const driver = setting('driver', 'choice', {
            value: 'decision-model',
            has_value: true,
            options: {'decision-model': 'Decision model (Jev / TypeSafe)', 'openai-compatible': 'OpenAI-compatible'},
        });
        const {wrapper: w, drafts} = mountTab([
            {code: 'm', name: 'M', description: '', enabled: true, settings: [driver]},
        ]);
        const radios = w.findAll('[data-setting="driver"] input[type="radio"]');
        expect(radios).toHaveLength(2);
        expect((radios[0]!.element as HTMLInputElement).checked).toBe(true);
        expect(w.find('[data-setting="driver"]').text()).toContain('OpenAI-compatible');
        await radios[1]!.setValue(true);
        expect(drafts.m!.settings.driver).toBe('openai-compatible');
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
        const {wrapper: w} = mountTab([{code: 'm', name: 'M', description: '', enabled: true, settings}], {
            errors: {m: {'mods.m.settings.s': 'Bad value'}},
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

    it('lays trust settings out as designed sections with stats and an advanced group', () => {
        const trustSettings = [
            {...setting('max_bonus', 'integer'), value: 3, has_value: true},
            {...setting('window_days', 'integer'), value: 7, has_value: true},
            {...setting('interval_minutes', 'integer'), value: 15, has_value: true},
        ];
        const {wrapper: w} = mountTab([
            {code: 'trust', name: 'Trust', description: '', enabled: true, settings: trustSettings},
        ]);
        expect(w.find('[data-section="Score bonus"] [data-setting="max_bonus"]').exists()).toBe(true);
        expect(w.find('details.mod-advanced [data-setting="window_days"]').exists()).toBe(true);
        const stats = w.findAll('[data-test="mod-stat"]').map((n) => n.text());
        expect(stats.join(' ')).toContain('+3 Score');
        expect(stats.join(' ')).toContain('7 days');
        expect(stats.join(' ')).toContain('every 15 min');
    });

    it('groups mods by purpose with unknown mods under Other, and expands settings only when on', async () => {
        const {wrapper: w, drafts} = mountTab([
            {
                code: 'whamageddon',
                name: 'Wham',
                description: 'Festive',
                enabled: false,
                settings: [setting('s', 'string')],
            },
            {code: 'trust', name: 'Trust', description: '', enabled: true, settings: [setting('s', 'string')]},
            {code: 'custom', name: 'Custom', description: '', enabled: false, settings: []},
        ]);
        expect(w.findAll('[data-group]').map((g) => g.attributes('data-group'))).toEqual([
            'Moderation',
            'Fun',
            'Other',
        ]);
        expect(w.find('[data-test="mod-settings-trust"]').exists()).toBe(true);
        expect(w.find('[data-test="mod-settings-whamageddon"]').exists()).toBe(false);
        expect(w.find('[data-mod]').exists()).toBe(false);
        await w.find('#mod-whamageddon-enabled').setValue(true);
        expect(drafts.whamageddon!.enabled).toBe(true);
        expect(w.find('[data-test="mod-settings-whamageddon"]').exists()).toBe(true);
    });

    describe('AI Request Review card', () => {
        const aiSettings = (hasKey: boolean, extra: Record<string, unknown> = {}) => [
            setting('driver', 'choice', {
                value: 'decision-model',
                has_value: true,
                options: {
                    'decision-model': 'Decision model (Jev / TypeSafe)',
                    'openai-compatible': 'OpenAI-compatible',
                },
            }),
            setting('api_key', 'secret', {has_value: hasKey}),
            setting('confidence_percentage', 'integer', {value: 70, has_value: true, ...extra}),
            setting('include_party_context', 'boolean', {value: true, has_value: true}),
            setting('timeout', 'integer', {value: 10, has_value: true}),
            setting('guidelines', 'text', {value: 'Keep it clean', has_value: true}),
        ];
        const ai = (hasKey = true, enabled = true) => ({
            code: 'ai-request-review',
            name: 'AI Request Review',
            description: 'Asks an AI model',
            enabled,
            settings: aiSettings(hasKey),
        });

        it('uses the shared ModCard in the party tab and on the admin page', () => {
            const {wrapper: party} = mountTab([ai()]);
            expect(party.findAllComponents(ModCard)).toHaveLength(1);
            const admin = mount(AdminMods, {props: {mods: [ai()]}});
            expect(admin.findAllComponents(ModCard)).toHaveLength(1);
            expect(admin.text()).toContain('Installed on this instance.');
        });

        it('collapses off mods to one row without a settings button', () => {
            const {wrapper: w} = mountTab([ai(true, false)]);
            expect(w.find('[data-test="mod-toggle"]').exists()).toBe(false);
            expect(w.find('[data-test="mod-stats"]').exists()).toBe(false);
            expect(w.find('[data-test="mod-status"]').text()).toBe('Off');
        });

        it('shows the On pill, stat strip and a settings toggle for on mods', async () => {
            const {wrapper: w} = mountTab([ai()]);
            expect(w.find('[data-test="mod-status"]').text()).toBe('On');
            const stats = w.findAll('[data-test="mod-stat"]').map((c) => c.text());
            expect(stats).toEqual(['EngineDecision model', 'API keySaved']);
            expect(w.find('[data-test="mod-body"]').attributes('style')).toBeUndefined();
            await w.find('[data-test="mod-toggle"]').trigger('click');
            expect(w.find('[data-test="mod-body"]').attributes('style')).toContain('display: none');
        });

        it('sets the threshold from presets and explains it in plain language', async () => {
            const {wrapper: w, drafts} = mountTab([ai()]);
            const explain = () => w.find('[data-test="threshold-explanation"]').text();
            expect(explain()).toContain('At 70%, the model has to be at least 70% sure');
            expect(explain()).toContain('85% sure');
            expect(explain()).toContain('55% sure');
            const presets = w.findAll('[data-test="threshold-preset"]');
            expect(presets.map((p) => p.text())).toEqual(['Strict 30%', 'Balanced 50%', 'Lenient 80%']);
            await presets[0]!.trigger('click');
            expect(drafts['ai-request-review']!.settings.confidence_percentage).toBe(30);
            expect(explain()).toContain('At 30%');
            await w.find('[data-test="threshold-range"]').setValue('80');
            expect(drafts['ai-request-review']!.settings.confidence_percentage).toBe(80);
        });

        it('appends example text from chips and counts characters', async () => {
            const {wrapper: w, drafts} = mountTab([ai()]);
            expect(w.find('[data-test="char-count"]').text()).toBe('13 characters');
            const chips = w.findAll('[data-test="guideline-example"]');
            expect(chips.map((c) => c.text())).toEqual([
                'LAN-friendly',
                'Family-friendly',
                'No spoken word',
                'Time-of-day rule',
            ]);
            await chips[0]!.trigger('click');
            const value = String(drafts['ai-request-review']!.settings.guidelines);
            expect(value.startsWith('Keep it clean\n')).toBe(true);
            expect(value).toContain('LAN-friendly');
            expect(w.find('[data-test="char-count"]').text()).toBe(`${[...value].length} characters`);
        });

        it('shows the saved key indicator and only edits the key after Replace key', async () => {
            const {wrapper: w, drafts} = mountTab([ai(true)]);
            expect(w.find('[data-test="key-saved"]').text()).toContain('Saved');
            expect(w.find('[data-test="ai-setup"]').exists()).toBe(false);
            expect(w.find('[data-setting="api_key"] input').attributes('readonly')).toBeDefined();
            await w.find('[data-test="replace-key"]').trigger('click');
            await w.find('[data-setting="api_key"] input').setValue('sk-new');
            expect(drafts['ai-request-review']!.settings.api_key).toBe('sk-new');
        });

        it('shows the setup state and locks the dependent sections when no key is saved', async () => {
            const {wrapper: w, drafts} = mountTab([ai(false)]);
            expect(w.find('[data-test="ai-setup"]').text()).toContain('Add an API key to start reviewing');
            expect(w.find('[data-test="mod-status"]').text()).toBe('Needs an API key');
            expect(w.find('[data-test="ai-gated"]').attributes('disabled')).toBeDefined();
            await w.find('[data-test="ai-setup"] input').setValue('sk-first');
            expect(drafts['ai-request-review']!.settings.api_key).toBe('sk-first');
        });

        it('keeps advanced settings in a disclosure and shows errors per setting', () => {
            const {wrapper: w} = mountTab([ai()], {
                errors: {'ai-request-review': {'mods.ai-request-review.settings.guidelines': 'Too long'}},
            });
            const advanced = w.find('[data-test="ai-advanced"]');
            expect(advanced.find('[data-setting="timeout"]').exists()).toBe(true);
            expect(advanced.attributes('open')).toBeUndefined();
            expect(w.find('[data-setting="guidelines"] [data-test="setting-error"]').text()).toBe('Too long');
        });
    });
});
