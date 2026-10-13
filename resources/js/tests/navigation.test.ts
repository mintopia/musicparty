import {mount} from '@vue/test-utils';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import AppLayout from '@/layouts/AppLayout.vue';
import {buildNavigation, isActive} from '@/lib/navigation';
import type {NavItem} from '@/lib/navigation';
import type {SharedProps} from '@/types';

const state = vi.hoisted(() => ({props: {} as unknown, url: '/'}));

vi.mock('@inertiajs/vue3', () => ({
    Head: {render: () => null},
    usePage: () => ({props: state.props, url: state.url}),
}));
vi.mock('@/components/FlashMessages.vue', () => ({default: {render: () => null}}));
vi.mock('@/composables/useColorMode', () => ({useColorMode: () => undefined}));

const {route, party} = vi.hoisted(() => ({
    route: (url: string) => () => ({url}),
    party: (code: string) => ({url: `/parties/${code}`}),
}));

vi.mock('@/routes', () => ({
    home: route('/'),
    login: route('/login'),
    logout: route('/logout'),
    pulse: route('/pulse'),
}));
vi.mock('@/routes/admin', () => ({dashboard: route('/admin'), unimpersonate: route('/admin/unimpersonate')}));
vi.mock('@/routes/admin/settings', () => ({index: route('/admin/settings')}));
vi.mock('@/routes/admin/users', () => ({index: route('/admin/users')}));
vi.mock('@/routes/horizon', () => ({index: route('/horizon')}));
vi.mock('@/routes/user', () => ({profile: route('/user/profile')}));
vi.mock('@/routes/parties', () => ({
    create: route('/parties/create'),
    show: (code: string) => party(code),
    edit: (code: string) => ({url: `/parties/${code}/edit`}),
    search: (code: string) => ({url: `/parties/${code}/search`}),
    tv: (code: string) => ({url: `/parties/${code}/tv`}),
    youtube: (code: string) => ({url: `/parties/${code}/youtube`}),
    player: (code: string) => ({url: `/parties/${code}/player`}),
}));
vi.mock('@/routes/parties/moderation', () => ({index: (code: string) => ({url: `/parties/${code}/moderation`})}));
vi.mock('@/routes/parties/songs', () => ({index: (code: string) => ({url: `/parties/${code}/songs`})}));
vi.mock('@/routes/parties/users', () => ({index: (code: string) => ({url: `/parties/${code}/users`})}));

function makeProps(overrides: {
    user?: Partial<NonNullable<SharedProps['auth']['user']>> | null;
    parties?: SharedProps['auth']['parties'];
    can?: Partial<SharedProps['auth']['can']>;
    defaultParty?: string | null;
}): SharedProps {
    const user =
        overrides.user === null
            ? null
            : {id: 1, nickname: 'Jess', avatar: '/a.png', roles: [], impersonating: false, ...overrides.user};

    return {
        auth: {user, parties: overrides.parties ?? [], can: {admin: false, createParty: false, ...overrides.can}},
        site: {
            defaultParty: overrides.defaultParty ?? null,
            logoLight: null,
            favicon: null,
            terms: null,
            privacyPolicy: null,
        },
        instanceName: 'Music Party',
    } as unknown as SharedProps;
}

function titles(items: NavItem[]): string[] {
    return items.map((item) => item.title);
}

describe('buildNavigation', () => {
    it('shows only Home and Login to guests', () => {
        const items = buildNavigation(makeProps({user: null}));

        expect(titles(items)).toEqual(['Home', 'Login']);
        expect(items.find((item) => item.key === 'login')?.href).toBe('/login');
    });

    it('shows Profile and Logout, but no Login, to signed-in users', () => {
        const items = titles(buildNavigation(makeProps({})));

        expect(items).toEqual(['Home', 'Profile', 'Logout']);
    });

    it('hides Home on the default party', () => {
        expect(titles(buildNavigation(makeProps({defaultParty: 'ABC'})))).not.toContain('Home');
    });

    it('gates manager-only party links on canManage', () => {
        const member = buildNavigation(makeProps({parties: [{code: 'ABC', name: 'Fun', canManage: false}]}));
        const manager = buildNavigation(makeProps({parties: [{code: 'ABC', name: 'Fun', canManage: true}]}));
        const links = (items: NavItem[]) => titles(items.find((item) => item.key === 'party:ABC')?.children ?? []);

        expect(links(member)).toEqual(['Queue', 'Search']);
        expect(links(manager)).toEqual([
            'Queue',
            'Search',
            'Songs',
            'Users',
            'TV Mode',
            'YouTube Player',
            'Web Player',
            'Flagged',
            'Moderation',
            'Mods',
            'Settings',
        ]);
    });

    it('gates Create Party on the createParty permission', () => {
        expect(titles(buildNavigation(makeProps({can: {createParty: true}})))).toContain('Create Party');
        expect(titles(buildNavigation(makeProps({})))).not.toContain('Create Party');
    });

    it('gates the admin menu on admin permission', () => {
        const admin = buildNavigation(makeProps({can: {admin: true}})).find((item) => item.key === 'admin');

        expect(titles(admin?.children ?? [])).toEqual(['Dashboard', 'Users', 'Settings', 'Mods', 'Pulse', 'Horizon']);
        expect(titles(buildNavigation(makeProps({})))).not.toContain('Admin');
    });
});

describe('isActive', () => {
    const partyItem: NavItem = {
        key: 'party:ABC',
        title: 'Fun',
        icon: '',
        children: [{title: 'Queue', href: '/parties/ABC'}],
    };
    const profileItem: NavItem = {key: 'profile', title: 'Profile', icon: '', href: '/user/profile'};
    const adminItem: NavItem = {
        key: 'admin',
        title: 'Admin',
        icon: '',
        children: [{title: 'Users', href: '/admin/users'}],
    };

    it('matches the exact path, sub-paths, trailing slashes and query strings', () => {
        expect(isActive(profileItem, '/user/profile')).toBe(true);
        expect(isActive(profileItem, '/user/profile/')).toBe(true);
        expect(isActive(profileItem, '/user/profile?tab=1')).toBe(true);
        expect(isActive(profileItem, '/user/profile/linked')).toBe(true);
    });

    it('does not match a path that only shares a prefix', () => {
        expect(isActive(profileItem, '/user/profiles')).toBe(false);
        expect(isActive(partyItem, '/parties/ABCD')).toBe(false);
    });

    it('activates a party for any of its pages and an admin menu for any child', () => {
        expect(isActive(partyItem, '/parties/ABC/songs')).toBe(true);
        expect(isActive(adminItem, '/admin/users')).toBe(true);
        expect(isActive(adminItem, '/user/profile')).toBe(false);
    });
});

describe('AppLayout navigation', () => {
    beforeEach(() => {
        state.url = '/';
    });

    function mountLayout(props: SharedProps, url = '/') {
        state.props = props;
        state.url = url;
        return mount(AppLayout);
    }

    const sidebarText = (wrapper: ReturnType<typeof mountLayout>) => wrapper.get('#sidebar-menu').text();

    it('renders Login and no Profile or Logout for guests', () => {
        const text = sidebarText(mountLayout(makeProps({user: null})));

        expect(text).toContain('Login');
        expect(text).not.toContain('Profile');
        expect(text).not.toContain('Logout');
    });

    it('hides Home when a default party is configured', () => {
        expect(sidebarText(mountLayout(makeProps({defaultParty: 'ABC'})))).not.toContain('Home');
    });

    it('renders manager-only links and the admin menu only when permitted', () => {
        const plain = sidebarText(mountLayout(makeProps({parties: [{code: 'ABC', name: 'Fun', canManage: false}]})));
        const privileged = sidebarText(
            mountLayout(makeProps({parties: [{code: 'ABC', name: 'Fun', canManage: true}], can: {admin: true}})),
        );

        expect(plain).not.toContain('Moderation');
        expect(plain).not.toContain('Admin');
        expect(privileged).toContain('Moderation');
        expect(privileged).toContain('Horizon');
    });

    it('marks the matching item active', () => {
        const wrapper = mountLayout(makeProps({}), '/user/profile');
        const active = wrapper.findAll('li.nav-item.active').map((li) => li.text());

        expect(active).toEqual(['Profile']);
    });

    it('shows the impersonation banner with a leave link only while impersonating', () => {
        const banner = mountLayout(makeProps({user: {impersonating: true, nickname: 'Sam'}}));
        const leave = banner.get('a.btn-primary');

        expect(banner.text()).toContain('You are impersonating Sam');
        expect(leave.attributes('href')).toBe('/admin/unimpersonate');
        expect(mountLayout(makeProps({})).text()).not.toContain('impersonating');
    });

    it('toggles the mobile menu and reflects it in aria-expanded', async () => {
        const wrapper = mountLayout(makeProps({}));
        const toggler = wrapper.get('button.navbar-toggler');

        expect(toggler.attributes('aria-expanded')).toBe('false');
        expect(wrapper.get('#sidebar-menu').classes()).not.toContain('show');

        await toggler.trigger('click');
        expect(toggler.attributes('aria-expanded')).toBe('true');
        expect(wrapper.get('#sidebar-menu').classes()).toContain('show');

        await toggler.trigger('click');
        expect(toggler.attributes('aria-expanded')).toBe('false');
    });

    it('toggles dropdowns and reflects it in aria-expanded', async () => {
        const wrapper = mountLayout(makeProps({can: {admin: true}}));
        const toggle = wrapper.get('a.dropdown-toggle');
        const menu = wrapper.get('.dropdown-menu');

        expect(toggle.attributes('aria-expanded')).toBe('false');
        expect(menu.classes()).not.toContain('show');

        await toggle.trigger('click');
        expect(toggle.attributes('aria-expanded')).toBe('true');
        expect(menu.classes()).toContain('show');

        await toggle.trigger('click');
        expect(toggle.attributes('aria-expanded')).toBe('false');
    });

    it('opens the dropdown of the active section by default', () => {
        const wrapper = mountLayout(makeProps({can: {admin: true}}), '/admin/users');

        expect(wrapper.get('a.dropdown-toggle').attributes('aria-expanded')).toBe('true');
    });
});
