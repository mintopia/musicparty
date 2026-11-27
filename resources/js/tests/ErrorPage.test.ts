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
});
