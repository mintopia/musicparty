import {mount} from '@vue/test-utils';
import {describe, expect, it, vi} from 'vitest';
import AppliedEntropyFooter from '@/components/AppliedEntropyFooter.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import BareLayout from '@/layouts/BareLayout.vue';
import GuestLayout from '@/layouts/GuestLayout.vue';

const state = vi.hoisted(() => ({
    props: {
        auth: {user: null, parties: [], can: {admin: false, createParty: false}},
        instanceName: 'Music Party',
        theme: {darkMode: false},
        site: {
            logoLight: null,
            logoDark: null,
            favicon: null,
            coverImage: null,
            terms: 'https://example.test/terms',
            privacyPolicy: '/privacy',
        },
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

describe('AppliedEntropyFooter', () => {
    it('has the credit, Source link and licence, and nothing else', () => {
        const wrapper = mount(AppliedEntropyFooter);

        const credit = wrapper.get('.ae-footer__credit');
        expect(credit.attributes('href')).toBe('https://appliedentropy.dev');
        expect(credit.text()).toBe('An Applied Entropy project');
        expect(wrapper.findAll('img.ae-footer__mark')).toHaveLength(2);
        expect(wrapper.get('.ae-footer__links a').attributes('href')).toBe('https://github.com/mintopia/musicparty');
        expect(wrapper.get('.ae-footer__links a').text()).toBe('Source');
        expect(wrapper.get('.ae-footer__legal').text()).toBe('© 2026 · MIT licence');
        expect(wrapper.text()).not.toMatch(/Terms|Privacy|GitHub|All rights reserved|Mintopia/);
    });
});

describe.each([
    ['main app layout', () => mount(AppLayout, {slots: {default: '<p>page</p>'}})],
    ['login (guest) layout', () => mount(GuestLayout, {slots: {default: '<p>login</p>'}})],
])('%s', (_name, mountLayout) => {
    it('shows one Applied Entropy footer replacing the old contents', () => {
        const wrapper = mountLayout();

        expect(wrapper.findAll('footer')).toHaveLength(1);
        const footer = wrapper.get('footer.ae-footer');
        expect(footer.get('.container-xl .ae-footer__credit').attributes('href')).toBe('https://appliedentropy.dev');
        expect(footer.text()).toContain('© 2026 · MIT licence');
        expect(footer.text()).not.toMatch(/Terms|Privacy|GitHub|All rights reserved|Mintopia/);
    });
});

describe('TV and bare layout', () => {
    it('has no footer', () => {
        const wrapper = mount(BareLayout, {slots: {default: '<p>tv</p>'}});

        expect(wrapper.find('footer').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('Applied Entropy');
    });
});
