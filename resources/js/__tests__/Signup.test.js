import {enableAutoUnmount, mount} from '@vue/test-utils';
import {afterEach, describe, expect, it, vi} from 'vitest';

const post = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    Head: {render: () => null},
    router: {post: (...args) => post(...args)},
    useForm: (data) => ({...data, errors: {}, processing: false, post: vi.fn()}),
}));

import Signup from '../pages/Signup.vue';

enableAutoUnmount(afterEach);

describe('Signup', () => {
    it('shows the avatar, heading and nickname hint', () => {
        const wrapper = mount(Signup, {props: {nickname: 'sam', avatarUrl: 'https://img/a.png'}});

        expect(wrapper.text()).toContain('Create Account');
        expect(wrapper.text()).toContain('Your preferred nickname');
        expect(wrapper.find('[data-testid="avatar"]').attributes('src')).toBe('https://img/a.png');
        expect(wrapper.find('input[name="terms"]').exists()).toBe(false);
    });

    it.each([
        ['terms only', 'https://t', null, ['Terms and Conditions']],
        ['privacy only', null, 'https://p', ['Privacy Policy']],
        ['both', 'https://t', 'https://p', ['Terms and Conditions', 'Privacy Policy']],
    ])('links the configured documents: %s', (_, termsUrl, privacyUrl, labels) => {
        const wrapper = mount(Signup, {props: {termsUrl, privacyUrl}});

        expect(wrapper.find('input[name="terms"]').exists()).toBe(true);
        expect(wrapper.findAll('label a').map((a) => a.text())).toEqual(labels);
    });

    it('logs out on Cancel', async () => {
        const wrapper = mount(Signup);

        await wrapper.find('[data-testid="cancel"]').trigger('click');

        expect(post).toHaveBeenCalledWith('/logout');
    });
});
