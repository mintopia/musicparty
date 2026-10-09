import {beforeEach, describe, expect, it, vi} from 'vitest';
import {mount} from '@vue/test-utils';
import {h} from 'vue';

const page = vi.hoisted(() => ({props: {}, url: '/admin/theme'}));
const router = vi.hoisted(() => ({put: vi.fn(), delete: vi.fn()}));

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => page,
    router,
    Head: {render: () => null},
    Link: {props: ['href'], render() { return h('a', {href: this.href}, this.$slots.default?.()); }},
}));

import Theme from '../pages/Admin/Theme.vue';

const scheme = (extra = {}) => ({text: '#000000', background: '#ffffff', surface: '#ffffff', primary: '#066fd1', ...extra});
const props = (extra = {}) => ({
    theme: {light: scheme(), dark: scheme({text: '#ffffff', background: '#000000', surface: '#111111'}), font: 'inter'},
    defaults: {light: scheme(), dark: scheme(), font: 'inter'},
    fonts: [{value: 'inter', label: 'Inter'}, {value: 'system', label: 'System'}],
    tokens: [
        {key: 'primary', label: 'Primary'},
        {key: 'background', label: 'Background'},
        {key: 'surface', label: 'Surface'},
        {key: 'text', label: 'Text'},
    ],
    warnings: [],
    ...extra,
});

const hex = (wrapper, code, key) => wrapper.find(`[data-scheme="${code}"] [data-token="${key}"] [data-hex]`);

beforeEach(() => {
    router.put.mockReset();
    router.delete.mockReset();
    page.props = {errors: {}};
});

describe('Admin Theme', () => {
    it('renders a row per token per scheme', () => {
        const wrapper = mount(Theme, {props: props()});
        expect(wrapper.findAll('[data-scheme="light"] [data-token]')).toHaveLength(4);
        expect(wrapper.findAll('[data-scheme="dark"] [data-token]')).toHaveLength(4);
        expect(hex(wrapper, 'light', 'primary').element.value).toBe('#066fd1');
    });

    it('shows server warnings initially', () => {
        const wrapper = mount(Theme, {
            props: props({warnings: [{scheme: 'dark', pair: 'text/surface', ratio: 3.2}]}),
        });
        expect(wrapper.find('[role="status"]').text()).toContain('Dark: text on surface 3.2:1, needs 4.5:1');
    });

    it('shows a live warning for low contrast and clears it when fixed', async () => {
        const wrapper = mount(Theme, {props: props()});
        expect(wrapper.findAll('[data-warning]')).toHaveLength(0);

        await hex(wrapper, 'light', 'text').setValue('#eeeeee');
        const text = wrapper.find('[role="status"]').text();
        expect(text).toContain('Light: text on background');
        expect(text).toContain('needs 4.5:1');

        await hex(wrapper, 'light', 'text').setValue('#000000');
        expect(wrapper.findAll('[data-warning]')).toHaveLength(0);
    });

    it('ignores partial hex input', async () => {
        const wrapper = mount(Theme, {props: props()});
        await hex(wrapper, 'light', 'text').setValue('#ee');
        expect(wrapper.findAll('[data-warning]')).toHaveLength(0);
    });

    it('applies draft colours to the preview scope only', async () => {
        const wrapper = mount(Theme, {props: props()});
        await hex(wrapper, 'light', 'primary').setValue('#ff0000');
        expect(wrapper.find('[data-preview="light"]').attributes('style')).toContain('--color-primary: #ff0000');
        expect(wrapper.find('[data-preview="dark"]').attributes('style')).toContain('--color-primary: #066fd1');
    });

    it('shows field errors', () => {
        page.props = {errors: {'light.primary': 'Invalid colour.'}};
        const wrapper = mount(Theme, {props: props()});
        expect(wrapper.find('[data-scheme="light"] [data-token="primary"] [role="alert"]').text()).toBe('Invalid colour.');
    });

    it('saves the draft via PUT', async () => {
        const wrapper = mount(Theme, {props: props()});
        await hex(wrapper, 'dark', 'primary').setValue('#123456');
        await wrapper.find('form').trigger('submit');

        expect(router.put).toHaveBeenCalledTimes(1);
        const [url, body] = router.put.mock.calls[0];
        expect(url).toBe('/admin/theme');
        expect(body.dark.primary).toBe('#123456');
        expect(body.light.primary).toBe('#066fd1');
        expect(body.font).toBe('inter');
    });

    it('asks for confirmation before reset', async () => {
        const confirm = vi.spyOn(window, 'confirm');
        const wrapper = mount(Theme, {props: props()});

        confirm.mockReturnValueOnce(false);
        await wrapper.find('[data-action="reset"]').trigger('click');
        expect(router.delete).not.toHaveBeenCalled();

        confirm.mockReturnValueOnce(true);
        await wrapper.find('[data-action="reset"]').trigger('click');
        expect(router.delete).toHaveBeenCalledWith('/admin/theme', expect.anything());
        confirm.mockRestore();
    });
});
