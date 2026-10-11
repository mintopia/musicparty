import {beforeEach, describe, expect, it, vi} from 'vitest';
import {mount} from '@vue/test-utils';
import {h, reactive} from 'vue';

const page = vi.hoisted(() => ({props: {}, url: '/admin/providers/twitch/edit'}));
const put = vi.hoisted(() => vi.fn());
const transform = vi.hoisted(() => ({fn: null}));

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => page,
    router: {},
    useForm: (data) => {
        const form = reactive({
            ...data,
            errors: {},
            processing: false,
            transform(callback) { transform.fn = callback; return form; },
            put,
        });
        return form;
    },
    Head: {render: () => null},
    Link: {props: ['href'], render() { return h('a', {href: this.href}, this.$slots.default?.()); }},
}));

import Edit from '../pages/Admin/Providers/Edit.vue';

const provider = (extra = {}) => ({
    code: 'twitch', name: 'Twitch', enabled: false, supports_auth: true, auth_enabled: false, return_urls: [],
    fields: [
        {code: 'client_id', name: 'Client ID', secret: false, value: null},
        {code: 'client_secret', name: 'Client Secret', secret: true, value: null},
    ],
    ...extra,
});

beforeEach(() => {
    put.mockReset();
    transform.fn = null;
});

describe('Admin Provider Edit', () => {
    it('renders credential fields, both toggles, cancel and save', () => {
        const wrapper = mount(Edit, {props: {provider: provider()}});

        expect(wrapper.find('#field-client_id').attributes('type')).toBe('text');
        expect(wrapper.find('#field-client_secret').attributes('type')).toBe('password');
        expect(wrapper.findAll('[role="switch"]').map((s) => s.attributes('aria-label'))).toEqual(['Enabled', 'Authentication Enabled']);
        expect(wrapper.find('a[href="/admin/settings"]').text()).toBe('Cancel');
        expect(wrapper.find('button[type="submit"]').text()).toBe('Save');
    });

    it('hides the authentication toggle when the provider does not support it', () => {
        const wrapper = mount(Edit, {props: {provider: provider({supports_auth: false})}});

        expect(wrapper.findAll('[role="switch"]')).toHaveLength(1);
    });

    it('disables the authentication toggle until the provider is enabled', async () => {
        const wrapper = mount(Edit, {props: {provider: provider()}});
        const [enabled, auth] = wrapper.findAll('[role="switch"]');

        expect(auth.attributes('disabled')).toBeDefined();
        await enabled.trigger('click');
        expect(wrapper.findAll('[role="switch"]')[1].attributes('disabled')).toBeUndefined();
    });

    it('submits with PUT to the provider url', async () => {
        const wrapper = mount(Edit, {props: {provider: provider()}});

        await wrapper.find('form').trigger('submit');

        expect(put).toHaveBeenCalledWith('/admin/providers/twitch', expect.anything());
        expect(transform.fn({settings: {}, enabled: true, auth_enabled: true})).toEqual({settings: {}, enabled: true, auth_enabled: true});
    });
});
