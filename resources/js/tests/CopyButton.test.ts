import {mount} from '@vue/test-utils';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import CopyButton from '@/components/CopyButton.vue';

beforeEach(() => {
    vi.useFakeTimers();
});

afterEach(() => {
    vi.useRealTimers();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

describe('CopyButton', () => {
    it('writes the raw value and shows the copied state for about two seconds', async () => {
        const writeText = vi.fn().mockResolvedValue(undefined);
        vi.stubGlobal('navigator', {clipboard: {writeText}});
        const wrapper = mount(CopyButton, {props: {value: 'wss://h/app/k', label: 'Copy relay URL'}});

        expect(wrapper.get('button').attributes('aria-label')).toBe('Copy relay URL');
        await wrapper.get('button').trigger('click');
        await vi.advanceTimersByTimeAsync(0);

        expect(writeText).toHaveBeenCalledWith('wss://h/app/k');
        expect(wrapper.find('.ti-check').exists()).toBe(true);
        expect(wrapper.get('[role="status"]').text()).toBe('Copied');

        await vi.advanceTimersByTimeAsync(2100);
        expect(wrapper.find('.ti-copy').exists()).toBe(true);
        expect(wrapper.get('[role="status"]').text()).toBe('');
    });

    it('falls back to execCommand when the clipboard API is unavailable', async () => {
        vi.stubGlobal('navigator', {});
        const exec = vi.fn().mockReturnValue(true);
        Object.defineProperty(document, 'execCommand', {value: exec, configurable: true});
        const wrapper = mount(CopyButton, {props: {value: 'secret', label: 'Copy app secret'}});

        await wrapper.get('button').trigger('click');
        await vi.advanceTimersByTimeAsync(0);

        expect(exec).toHaveBeenCalledWith('copy');
        expect(wrapper.find('.ti-check').exists()).toBe(true);
        expect(document.querySelector('textarea')).toBeNull();
    });
});
