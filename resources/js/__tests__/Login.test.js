import {enableAutoUnmount, mount} from '@vue/test-utils';
import {afterEach, describe, expect, it, vi} from 'vitest';

let flash = {};

vi.mock('@inertiajs/vue3', () => ({
    Head: {render: () => null},
    usePage: () => ({props: {flash, site: {}}}),
}));

import Login from '../pages/Login.vue';

enableAutoUnmount(afterEach);

describe('Login flashes', () => {
    it('renders info and success flashes', () => {
        flash = {info: 'You have been logged out', success: 'You have been logged in'};
        const wrapper = mount(Login, {props: {providers: []}});

        expect(wrapper.text()).toContain('You have been logged out');
        expect(wrapper.text()).toContain('You have been logged in');
    });

    it('renders an error flash', () => {
        flash = {error: 'Unable to login'};
        const wrapper = mount(Login, {props: {providers: []}});

        expect(wrapper.text()).toContain('Unable to login');
    });

    it('renders no alerts when there are no flashes', () => {
        flash = {};
        const wrapper = mount(Login, {props: {providers: []}});

        expect(wrapper.find('[data-testid="flash-alerts"]').exists()).toBe(false);
    });
});
