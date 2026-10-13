import {mount} from '@vue/test-utils';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {defineComponent, ref, type Ref} from 'vue';
import {useProgress} from '@/composables/useProgress';
import type {PlaybackState} from '@/types';

const base: PlaybackState = {
    isPlaying: true,
    trackId: 't',
    linkedFromId: null,
    durationMs: 200_000,
    progressMs: 10_000,
    sampledAt: '2026-01-01T00:00:00.000Z',
    shuffle: false,
    repeat: 'off',
    deviceId: null,
    deviceName: null,
    source: 'spotify',
};

function host(state: Ref<PlaybackState | null>) {
    let result!: ReturnType<typeof useProgress>;
    const wrapper = mount(
        defineComponent({
            setup() {
                result = useProgress(state);
                return () => null;
            },
        }),
    );
    return {wrapper, result};
}

describe('useProgress', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-01-01T00:00:05.000Z'));
    });
    afterEach(() => vi.useRealTimers());

    it('anchors on sampledAt and advances while playing', async () => {
        const {wrapper, result} = host(ref({...base}));
        expect(result.progressMs.value).toBe(15_000);
        expect(result.elapsedLabel.value).toBe('0:15');

        await vi.advanceTimersByTimeAsync(3000);
        expect(result.progressMs.value).toBe(18_000);
        expect(result.progressPercent.value).toBeCloseTo(9);
        wrapper.unmount();
    });

    it('clamps to the duration', async () => {
        const {wrapper, result} = host(ref({...base, durationMs: 16_000}));
        await vi.advanceTimersByTimeAsync(5000);
        expect(result.progressMs.value).toBe(16_000);
        wrapper.unmount();
    });

    it('does not advance or tick while paused', async () => {
        const {wrapper, result} = host(ref({...base, isPlaying: false}));
        expect(vi.getTimerCount()).toBe(0);
        await vi.advanceTimersByTimeAsync(5000);
        expect(result.progressMs.value).toBe(10_000);
        wrapper.unmount();
    });

    it('clears the interval on pause and unmount', async () => {
        const state = ref<PlaybackState | null>({...base});
        const {wrapper} = host(state);
        expect(vi.getTimerCount()).toBe(1);

        state.value = {...base, isPlaying: false};
        await wrapper.vm.$nextTick();
        expect(vi.getTimerCount()).toBe(0);

        state.value = {...base};
        await wrapper.vm.$nextTick();
        expect(vi.getTimerCount()).toBe(1);

        wrapper.unmount();
        expect(vi.getTimerCount()).toBe(0);
    });
});
