import {mount} from '@vue/test-utils';
import {describe, expect, it, vi} from 'vitest';
import ErrorPage from '@/pages/Error.vue';

const visit = vi.hoisted(() => vi.fn());
const user = vi.hoisted(() => ({value: null as {nickname: string} | null}));

vi.mock('@inertiajs/vue3', () => ({
    router: {visit},
    usePage: () => ({props: {auth: {user: user.value}}}),
}));
vi.mock('@/layouts/AppLayout.vue', () => ({default: {template: '<div data-layout="app"><slot /></div>'}}));
vi.mock('@/layouts/GuestLayout.vue', () => ({default: {template: '<div data-layout="guest"><slot /></div>'}}));
vi.mock('@/routes', () => ({home: () => ({url: '/'}), login: () => ({url: '/login'})}));
vi.mock('@/components/Breadcrumbs.vue', () => ({default: {template: '<nav />'}}));

const base = {status: 404, title: "We can't find that page", message: 'Gone.', partyNotFound: false};
const partyNotFound = {...base, title: 'No party with that code', partyNotFound: true};

describe('Error page', () => {
    it('uses the guest layout for anonymous visitors and the app layout when logged in', () => {
        user.value = null;
        expect(mount(ErrorPage, {props: base}).find('[data-layout="guest"]').exists()).toBe(true);

        user.value = {nickname: 'Jess'};
        expect(mount(ErrorPage, {props: base}).find('[data-layout="app"]').exists()).toBe(true);
    });

    it('names its destinations and offers party code entry on a 404', async () => {
        const wrapper = mount(ErrorPage, {props: base});

        expect(wrapper.text()).toContain('Go to Home');
        expect(wrapper.find('[data-test="party-code-form"]').exists()).toBe(false);

        await wrapper
            .findAll('button')
            .find((button) => button.text().includes('Enter party code'))!
            .trigger('click');

        expect(wrapper.find('[data-test="party-code-form"]').exists()).toBe(true);
    });

    it('offers Try again on a 500', () => {
        const wrapper = mount(ErrorPage, {props: {...base, status: 500, title: 'Oops'}});

        expect(wrapper.text()).toContain('Try again');
        expect(wrapper.text()).toContain('Go to Home');
    });

    it('rejects invalid party codes without navigating', async () => {
        visit.mockClear();
        const wrapper = mount(ErrorPage, {props: partyNotFound});

        await wrapper.get('input').setValue('AB1');
        await wrapper.get('form').trigger('submit');

        expect(wrapper.get('[data-test="party-code-hint"]').text()).toBe('Party codes are four letters, no digits.');
        expect(visit).not.toHaveBeenCalled();
    });

    it('uppercases a valid code and goes to the party', async () => {
        visit.mockClear();
        const wrapper = mount(ErrorPage, {props: partyNotFound});

        await wrapper.get('input').setValue('kxqc');
        await wrapper.get('form').trigger('submit');

        expect(visit).toHaveBeenCalledWith('/parties/KXQC');
    });

    it('pre-fills the typed code and shows the hint for a valid-looking code', () => {
        const wrapper = mount(ErrorPage, {props: {...partyNotFound, typedCode: 'KXQC'}});

        expect((wrapper.get('input').element as HTMLInputElement).value).toBe('KXQC');
        expect(wrapper.get('[data-test="party-code-hint"]').text()).toBe('Four letters, no digits.');
        expect(wrapper.text()).not.toContain('Go to Home');
    });

    it('explains digits and short codes inline when pre-filled', () => {
        const digit = mount(ErrorPage, {props: {...partyNotFound, typedCode: 'KX9B'}});
        expect((digit.get('input').element as HTMLInputElement).value).toBe('KX9B');
        expect(digit.get('[data-test="party-code-hint"]').text()).toBe('Party codes are four letters, no digits.');
        expect(digit.get('input').classes()).toContain('is-invalid');

        const short = mount(ErrorPage, {props: {...partyNotFound, typedCode: 'KXQ'}});
        expect(short.get('[data-test="party-code-hint"]').text()).toBe(
            'Party codes are four letters. You typed three.',
        );
    });

    it('renders server-provided actions with the first as primary and an icon', () => {
        const wrapper = mount(ErrorPage, {
            props: {
                ...base,
                status: 403,
                title: "You can't open Dashboard",
                icon: 'ti-lock',
                actions: [
                    {label: 'Open Mods settings', href: '/parties/ABCD/mods', icon: 'ti-settings'},
                    {label: 'Go to Moderation', href: '/parties/ABCD/moderation', icon: 'ti-shield-check'},
                ],
            },
        });

        const links = wrapper.findAll('[data-test="error-actions"] a');
        expect(links.map((link) => link.text())).toEqual(['Open Mods settings', 'Go to Moderation']);
        expect(links[0].classes()).toContain('btn-primary');
        expect(links[1].classes()).toContain('btn-outline-secondary');
        expect(wrapper.find('.mp-error-icon .ti-lock').exists()).toBe(true);
        expect(wrapper.find('.mp-error-code').exists()).toBe(false);
    });

    it('uses the anonymous layout style and copy with Login for guests on a 404', () => {
        user.value = null;
        const wrapper = mount(ErrorPage, {props: base});

        expect(wrapper.get('[data-test="error-page"]').classes()).not.toContain('mp-stage');
        expect(wrapper.get('h1').classes()).toContain('h3');
        expect(wrapper.get('p').text()).toBe('The link may be old. Enter a party code or login.');
        expect(wrapper.text()).toContain('Enter party code');
        expect(wrapper.text()).toContain('Login');
        expect(wrapper.text()).not.toContain('Go to Home');
        user.value = {nickname: 'Jess'};
    });
});
