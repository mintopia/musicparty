import {mount} from '@vue/test-utils';
import {describe, expect, it, vi} from 'vitest';
import AppFooter from '@/components/AppFooter.vue';
import BareLayout from '@/layouts/BareLayout.vue';
import GuestLayout from '@/layouts/GuestLayout.vue';
import AppLayout from '@/layouts/AppLayout.vue';

const state = vi.hoisted(() => ({
    props: {
        auth: {user: null, parties: [], can: {admin: false, createParty: false}},
        instanceName: 'Music Party',
        theme: {darkMode: false},
        site: {logoLight: null, logoDark: null, favicon: null, coverImage: null, terms: null, privacyPolicy: null},
        flash: {success: null, error: null, info: null, warning: null},
    } as Record<string, unknown>,
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: {render: () => null},
    Link: {props: ['href'], template: '<a :href="href"><slot /></a>'},
    usePage: () => ({props: state.props, url: '/'}),
}));
vi.mock('@/components/FlashMessages.vue', () => ({default: {render: () => null}}));
vi.mock('@/composables/useColorMode', () => ({useColorMode: () => undefined}));
vi.mock('@/lib/navigation', () => ({buildNavigation: () => [], isActive: () => false}));
vi.mock('@/routes', () => ({home: () => ({url: '/'}), logout: () => ({url: '/logout'})}));
vi.mock('@/routes/admin', () => ({unimpersonate: () => ({url: '/admin/unimpersonate'})}));
vi.mock('@/routes/user', () => ({profile: () => ({url: '/user/profile'})}));

describe('AppFooter', () => {
    it('credits Applied Entropy with the small mark, source link and licence', () => {
        const wrapper = mount(AppFooter);

        const credit = wrapper.get('.ae-footer__credit');
        expect(credit.attributes('href')).toBe('https://appliedentropy.dev');
        expect(credit.text()).toBe('An Applied Entropy project');
        expect(wrapper.findAll('img.ae-footer__mark')).toHaveLength(2);
        expect(wrapper.get('a[href="https://github.com/mintopia/musicparty"]').text()).toBe('Source');
        expect(wrapper.get('.ae-footer__legal').text()).toBe('© 2026 · MIT');
        expect(wrapper.text()).not.toContain('Making something useful out of chaos');
    });

    it('keeps the terms and privacy links only when configured', () => {
        expect(mount(AppFooter).text()).not.toContain('Privacy Policy');

        state.props.site = {
            ...(state.props.site as object),
            terms: 'https://example.test/terms',
            privacyPolicy: '/privacy',
        };
        const text = mount(AppFooter).text();
        state.props.site = {...(state.props.site as object), terms: null, privacyPolicy: null};

        expect(text).toContain('Terms and Conditions');
        expect(text).toContain('Privacy Policy');
    });
});

describe('layouts and the footer', () => {
    it('shows exactly one footer on the guest (login) layout', () => {
        const wrapper = mount(GuestLayout, {slots: {default: '<p>login</p>'}});

        expect(wrapper.findAll('footer')).toHaveLength(1);
        expect(wrapper.find('footer.ae-footer').exists()).toBe(true);
    });

    it('shows exactly one footer on the main app layout', () => {
        const wrapper = mount(AppLayout, {slots: {default: '<p>page</p>'}});

        expect(wrapper.findAll('footer')).toHaveLength(1);
        expect(wrapper.find('footer.ae-footer').exists()).toBe(true);
    });

    it('leaves the TV and bare layout without a footer', () => {
        const wrapper = mount(BareLayout, {slots: {default: '<p>tv</p>'}});

        expect(wrapper.find('footer').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('Applied Entropy');
    });
});
