import {beforeEach, describe, expect, it, vi} from 'vitest';
import {mount} from '@vue/test-utils';
import {defineComponent, h, nextTick} from 'vue';

const page = vi.hoisted(() => ({props: {}, url: '/', component: 'Home'}));
const router = vi.hoisted(() => ({post: vi.fn(), delete: vi.fn(), put: vi.fn()}));

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => page,
    router,
    Link: {props: ['href'], render() { return h('a', {href: this.href}, this.$slots.default?.()); }},
}));

import AdminLayout from '../Layouts/AdminLayout.vue';
import AppShell from '../Layouts/AppShell.vue';
import {usePageMeta} from '../composables/usePageMeta.js';
import {pageFallback} from '../lib/pageFallback.js';

const stubs = {ColourSchemeToggle: true, AppFooter: true};

const metaPage = (meta) => defineComponent({
    setup() {
        usePageMeta(meta);
        return () => h('p', {'data-testid': 'body'}, 'content');
    },
});

beforeEach(() => {
    router.post.mockReset();
    router.delete.mockReset();
    page.component = 'Home';
    page.url = '/';
    page.props = {
        appName: 'MP',
        auth: {user: {id: 1, name: 'Ann', avatarUrl: '/a.png', is_admin: true}},
        parties: [],
        flash: {},
        impersonating: null,
        adminLinks: [],
    };
});

describe('pageFallback', () => {
    it.each([
        ['Home', ['Home']],
        ['Login', ['Home', 'Login']],
        ['Signup', ['Home', 'Sign up']],
        ['Admin/Users/Index', ['Home', 'Admin', 'Users']],
        ['Admin/Dashboard', ['Home', 'Admin']],
        ['Party/Stats', ['Home', 'Stats']],
    ])('%s never exposes the raw component name', (component, labels) => {
        expect(pageFallback(component, {}).crumbs.map((c) => c.label)).toEqual(labels);
    });

    it('includes the party name for party pages', () => {
        const {crumbs} = pageFallback('Party/Stats', {party: {name: 'LAN', code: 'ABCD'}});
        expect(crumbs.map((c) => c.label)).toEqual(['Home', 'LAN', 'Stats']);
    });
});

describe('AppShell', () => {
    it('shows fallback breadcrumbs when the page declares none', () => {
        page.component = 'Login';
        const wrapper = mount(AppShell, {global: {stubs}});
        const text = wrapper.get('[data-testid="topbar"]').text();
        expect(text).toContain('Login');
        expect(text).not.toContain('Home / Login/');
    });

    it('shows page-declared breadcrumbs', async () => {
        page.component = 'Admin/Settings';
        const child = metaPage({title: 'Settings', crumbs: [{label: 'Home', href: '/'}, {label: 'Admin', href: '/admin'}, {label: 'Settings'}]});
        const wrapper = mount(AppShell, {global: {stubs}, slots: {default: () => h(child)}});
        await nextTick();
        const crumbs = wrapper.get('nav[aria-label="Breadcrumb"]');
        expect(crumbs.findAll('a').map((a) => a.attributes('href'))).toEqual(['/', '/admin']);
        expect(crumbs.get('[aria-current="page"]').text()).toBe('Settings');
    });

    it('opens and closes the mobile sidebar drawer', async () => {
        const wrapper = mount(AppShell, {global: {stubs}});
        expect(wrapper.find('[data-testid="sidebar-drawer"]').exists()).toBe(false);

        await wrapper.get('[data-testid="sidebar-toggle"]').trigger('click');
        expect(wrapper.find('[data-testid="sidebar-drawer"]').exists()).toBe(true);

        await wrapper.get('[data-testid="sidebar-backdrop"]').trigger('click');
        expect(wrapper.find('[data-testid="sidebar-drawer"]').exists()).toBe(false);
    });

    it('renders the impersonation banner and stops impersonating', async () => {
        page.props.impersonating = {name: 'Bob', stopUrl: '/admin/impersonate'};
        const wrapper = mount(AppShell, {global: {stubs}});
        const banner = wrapper.get('[data-testid="impersonation-banner"]');
        expect(banner.text()).toContain('You are impersonating Bob');

        await banner.get('[data-action="stop-impersonating"]').trigger('click');
        expect(router.delete).toHaveBeenCalledWith('/admin/impersonate');
    });

    it('omits the banner when not impersonating', () => {
        expect(mount(AppShell, {global: {stubs}}).find('[data-testid="impersonation-banner"]').exists()).toBe(false);
    });
});

describe('AdminLayout', () => {
    const mountAdmin = (meta) => {
        const child = metaPage(meta);
        return mount(AdminLayout, {global: {stubs}, slots: {default: () => h(child)}});
    };

    it('renders the page title as the H1 and flash alerts, without a tab strip', async () => {
        page.component = 'Admin/Parties/Index';
        page.props.flash = {success: 'Saved'};
        const wrapper = mountAdmin({title: 'Parties', crumbs: [{label: 'Home', href: '/'}, {label: 'Parties'}]});
        await nextTick();

        expect(wrapper.get('h1').text()).toBe('Parties');
        expect(wrapper.get('[data-testid="flash-alerts"]').text()).toContain('Saved');
        expect(wrapper.find('nav[aria-label="Admin"]').exists()).toBe(false);
        expect(wrapper.find('.max-w-5xl').exists()).toBe(false);
    });

    it('renders no H1 for pages that declare no meta', () => {
        page.component = 'Admin/Users/Index';
        const wrapper = mount(AdminLayout, {global: {stubs}, slots: {default: () => h('p', 'legacy')}});
        expect(wrapper.find('h1').exists()).toBe(false);
    });
});
