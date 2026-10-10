import {beforeEach, describe, expect, it, vi} from 'vitest';
import {mount} from '@vue/test-utils';
import {reactive} from 'vue';

const router = vi.hoisted(() => ({delete: vi.fn()}));
const posted = vi.hoisted(() => ({calls: []}));

vi.mock('@inertiajs/vue3', () => ({
    router,
    Head: {render: () => null},
    useForm: (data) => {
        const form = reactive({
            ...data,
            errors: {},
            processing: false,
            transform(fn) { this.transformer = fn; return this; },
            post(url, options) { posted.calls.push({url, options, data: this.transformer({...this.$snapshot()})}); },
            $snapshot() { const {errors, processing, transform, post, $snapshot, transformer, ...rest} = this; return JSON.parse(JSON.stringify(rest)); },
        });
        return form;
    },
}));

import Theme from '../pages/Party/Theme.vue';

const scheme = (extra = {}) => ({primary: '#066fd1', accent: '#f76707', background: '#ffffff', surface: '#ffffff', text: '#000000', danger: '#d63939', ...extra});
const props = (extra = {}) => ({
    party: {code: 'FRI1', name: 'Friday'},
    theme: {
        light: scheme(),
        dark: scheme({text: '#ffffff', background: '#000000'}),
        font: 'inter',
        tv_layout: 'default',
        overrides: {light: {primary: '#abcdef'}, dark: {}, font: null},
    },
    fonts: [{value: 'inter', label: 'Inter'}, {value: 'mono', label: 'Monospace'}],
    tokens: ['primary', 'accent', 'background', 'surface', 'text', 'danger'].map((key) => ({key, label: key})),
    layouts: [{value: 'default', label: 'Default'}, {value: 'compact', label: 'Compact'}],
    warnings: [],
    ...extra,
});

const hex = (w, code, key) => w.find(`[data-scheme="${code}"] [data-token="${key}"] [data-hex]`);

beforeEach(() => {
    posted.calls = [];
    router.delete.mockReset();
});

describe('Party Theme editor', () => {
    it('renders six tokens per scheme seeded from overrides, with inherited placeholders', () => {
        const w = mount(Theme, {props: props()});
        expect(w.findAll('[data-scheme="light"] [data-token]')).toHaveLength(6);
        expect(w.findAll('[data-scheme="dark"] [data-token]')).toHaveLength(6);
        expect(hex(w, 'light', 'primary').element.value).toBe('#abcdef');
        expect(hex(w, 'light', 'accent').element.value).toBe('#f76707');
    });

    it('posts overrides as multipart with clear-to-inherit sent as null', async () => {
        const w = mount(Theme, {props: props()});
        await w.get('[data-scheme="light"] [data-token="primary"] [data-action="clear"]').trigger('click');
        await hex(w, 'dark', 'accent').setValue('#112233');
        await w.get('#theme-layout').setValue('compact');
        await w.get('form').trigger('submit');

        const [call] = posted.calls;
        expect(call.url).toBe('/parties/FRI1/theme');
        expect(call.options.forceFormData).toBe(true);
        expect(call.data.light).toEqual({});
        expect(call.data.dark).toEqual({accent: '#112233'});
        expect(call.data.tv_layout).toBe('compact');
        expect(call.data.font).toBeNull();
        expect(call.data).not.toHaveProperty('logo');
    });

    it('sends remove flags for logo and background', async () => {
        const w = mount(Theme, {props: props()});
        await w.get('[data-remove="logo"]').setValue(true);
        await w.get('form').trigger('submit');
        expect(posted.calls[0].data.remove_logo).toBe(true);
        expect(posted.calls[0].data.remove_background).toBe(false);
    });

    it('shows advisory contrast warnings without blocking save', async () => {
        const w = mount(Theme, {props: props()});
        expect(w.findAll('[data-warning]')).toHaveLength(0);
        await hex(w, 'light', 'text').setValue('#fafafa');
        expect(w.findAll('[data-warning]').length).toBeGreaterThan(0);
        await w.get('form').trigger('submit');
        expect(posted.calls).toHaveLength(1);
    });

    it('resets via DELETE after confirmation', async () => {
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(true);
        const w = mount(Theme, {props: props()});
        await w.get('[data-action="reset"]').trigger('click');
        expect(router.delete).toHaveBeenCalledWith('/parties/FRI1/theme', {preserveScroll: true});
        confirm.mockReturnValue(false);
        router.delete.mockReset();
        await w.get('[data-action="reset"]').trigger('click');
        expect(router.delete).not.toHaveBeenCalled();
    });
});
