import {beforeEach, describe, expect, it, vi} from 'vitest';
import {flushPromises, mount} from '@vue/test-utils';

const page = vi.hoisted(() => ({props: {}}));
const put = vi.hoisted(() => vi.fn());

vi.mock('@inertiajs/vue3', () => ({usePage: () => page}));
vi.mock('axios', () => ({default: {put}}));

import ColourSchemeToggle from '../Components/ColourSchemeToggle.vue';

beforeEach(() => {
    document.documentElement.classList.remove('dark');
    localStorage.clear();
    window.matchMedia = () => ({matches: false, addEventListener() {}, removeEventListener() {}});
    put.mockReset();
    put.mockResolvedValue({});
});

const darkButton = (wrapper) => wrapper.find('[data-scheme="dark"]');

describe('ColourSchemeToggle', () => {
    it('renders three buttons with system pressed by default', () => {
        page.props = {auth: {user: null}};
        const wrapper = mount(ColourSchemeToggle);

        expect(wrapper.find('[data-testid="colour-scheme-toggle"]').exists()).toBe(true);
        expect(wrapper.findAll('button').map((b) => b.text())).toEqual(['Light', 'Dark', 'System']);
        expect(wrapper.find('[data-scheme="system"]').attributes('aria-pressed')).toBe('true');
    });

    it('applies dark and saves once for an authenticated user', async () => {
        page.props = {auth: {user: {id: 1}}};
        const wrapper = mount(ColourSchemeToggle);

        await darkButton(wrapper).trigger('click');
        await flushPromises();

        expect(document.documentElement.classList.contains('dark')).toBe(true);
        expect(darkButton(wrapper).attributes('aria-pressed')).toBe('true');
        expect(wrapper.find('[data-scheme="system"]').attributes('aria-pressed')).toBe('false');
        expect(put).toHaveBeenCalledTimes(1);
        expect(put).toHaveBeenCalledWith('/colour-scheme', {colour_scheme: 'dark'});
    });

    it('does not call the server for anonymous users', async () => {
        page.props = {auth: {user: null}};
        const wrapper = mount(ColourSchemeToggle);

        await darkButton(wrapper).trigger('click');
        await flushPromises();

        expect(document.documentElement.classList.contains('dark')).toBe(true);
        expect(put).not.toHaveBeenCalled();
    });

    it('keeps the local choice when the request rejects', async () => {
        page.props = {auth: {user: {id: 1}}};
        put.mockRejectedValue(new Error('boom'));
        const wrapper = mount(ColourSchemeToggle);

        await darkButton(wrapper).trigger('click');
        await flushPromises();

        expect(darkButton(wrapper).attributes('aria-pressed')).toBe('true');
        expect(document.documentElement.classList.contains('dark')).toBe(true);
    });
});
