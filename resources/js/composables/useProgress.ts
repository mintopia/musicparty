import {computed, onBeforeUnmount, ref, toValue, watch, type MaybeRefOrGetter} from 'vue';
import type {PlaybackState} from '@/types';

function useTicker(active: MaybeRefOrGetter<boolean>, restartOn: MaybeRefOrGetter<unknown>) {
    const now = ref(Date.now());
    let timer: ReturnType<typeof setInterval> | null = null;

    function stop(): void {
        if (timer !== null) {
            clearInterval(timer);
            timer = null;
        }
    }

    watch(
        [() => toValue(active), () => toValue(restartOn)],
        ([isActive]) => {
            stop();
            now.value = Date.now();
            if (isActive) {
                timer = setInterval(() => {
                    now.value = Date.now();
                }, 1000);
            }
        },
        {immediate: true},
    );

    onBeforeUnmount(stop);

    return now;
}

export function formatClock(ms: number): string {
    const total = Math.max(0, Math.floor(ms / 1000));
    return `${Math.floor(total / 60)}:${String(total % 60).padStart(2, '0')}`;
}

export function useProgress(state: MaybeRefOrGetter<PlaybackState | null>) {
    const now = useTicker(
        () => toValue(state)?.isPlaying ?? false,
        () => toValue(state),
    );

    const progressMs = computed(() => {
        const current = toValue(state);
        if (!current) {
            return 0;
        }

        const elapsed = current.isPlaying ? Math.max(0, now.value - Date.parse(current.sampledAt)) : 0;
        const position = current.progressMs + elapsed;

        return current.durationMs === null ? position : Math.min(position, current.durationMs);
    });

    const progressPercent = computed(() => {
        const duration = toValue(state)?.durationMs;
        return duration ? Math.min(100, (progressMs.value / duration) * 100) : 0;
    });

    const elapsedLabel = computed(() => formatClock(progressMs.value));

    return {progressMs, progressPercent, elapsedLabel};
}

export function useCountdown(endsAt: MaybeRefOrGetter<number | null>) {
    const now = useTicker(() => toValue(endsAt) !== null && toValue(endsAt)! > Date.now(), endsAt);

    const remainingMs = computed(() => {
        const target = toValue(endsAt);
        return target === null ? 0 : Math.max(0, target - now.value);
    });

    return {remainingMs, remainingLabel: computed(() => formatClock(remainingMs.value))};
}
