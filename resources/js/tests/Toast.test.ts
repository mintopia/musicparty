import {mount} from '@vue/test-utils';
import {describe, expect, it, vi} from 'vitest';
import Toast from '@/components/Toast.vue';

const props = vi.hoisted(() => ({
    value: {flash: {success: 'Saved', error: 'Broke', info: null, warning: null}},
}));

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({props: props.value}),
}));

describe('Toast', () => {
    it('shows flash messages and dismisses one', async () => {
        const wrapper = mount(Toast);

        expect(wrapper.findAll('.toast')).toHaveLength(2);
        expect(wrapper.text()).toContain('Saved');
        expect(wrapper.text()).toContain('Broke');

        await wrapper.get('.btn-close').trigger('click');

        expect(wrapper.findAll('.toast')).toHaveLength(1);
        expect(wrapper.text()).not.toContain('Saved');
    });

    it('shows a one-off message and emits dismiss', async () => {
        const wrapper = mount(Toast, {props: {message: 'Already requested', variant: 'danger'}});

        expect(wrapper.findAll('.toast')).toHaveLength(1);
        expect(wrapper.get('.toast').classes()).toContain('text-bg-danger');
        expect(wrapper.text()).toContain('Already requested');
        expect(wrapper.text()).not.toContain('Saved');

        await wrapper.get('.btn-close').trigger('click');

        expect(wrapper.emitted('dismiss')).toHaveLength(1);
    });

    it('auto-dismisses after the timeout', async () => {
        vi.useFakeTimers();
        const wrapper = mount(Toast, {props: {autoDismissMs: 1000}});

        await vi.advanceTimersByTimeAsync(1000);

        expect(wrapper.findAll('.toast')).toHaveLength(0);
        vi.useRealTimers();
    });
});
