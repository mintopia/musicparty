import {mount} from '@vue/test-utils';
import {describe, expect, it, vi} from 'vitest';
import AppliedEntropyCredit from '@/components/AppliedEntropyCredit.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import BareLayout from '@/layouts/BareLayout.vue';
import GuestLayout from '@/layouts/GuestLayout.vue';

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

describe('AppliedEntropyCredit', () => {
    it('links to Applied Entropy with the small mark in both variants', () => {
        const wrapper = mount(AppliedEntropyCredit);

        expect(wrapper.attributes('href')).toBe('https://appliedentropy.dev');
        expect(wrapper.text()).toBe('An Applied Entropy project');
        expect(wrapper.findAll('img.ae-credit__mark')).toHaveLength(2);
    });
});

describe('layouts and the credit', () => {
    it('replaces the Mintopia credit in the app layout footer and keeps the rest', () => {
        const footer = mount(AppLayout, {slots: {default: '<p>page</p>'}}).get('footer');

        expect(footer.find('.ae-credit').exists()).toBe(true);
        expect(footer.text()).not.toContain('Mintopia');
        expect(footer.text()).toContain('Copyright');
        expect(footer.text()).toContain('GitHub');
    });

    it('shows the credit in the login (guest) layout footer', () => {
        const footer = mount(GuestLayout, {slots: {default: '<p>login</p>'}}).get('footer');

        expect(footer.find('.ae-credit').exists()).toBe(true);
        expect(footer.text()).toContain('All rights reserved');
    });

    it('leaves the TV and bare layout without the credit', () => {
        const wrapper = mount(BareLayout, {slots: {default: '<p>tv</p>'}});

        expect(wrapper.find('footer').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('Applied Entropy');
    });
});
