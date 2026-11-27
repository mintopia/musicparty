import axios from 'axios';
import {computed, onBeforeUnmount, ref, toValue, watch, type MaybeRefOrGetter} from 'vue';
import {store as storeRating} from '@/routes/api/v1/parties/playedsongs/rate';
import type {LegacyPartyState, MyRating} from '@/types';

export type RatingValue = -1 | 0 | 1;

export function ratingErrorMessage(error: unknown): string {
    const status = (error as {response?: {status?: number}} | null)?.response?.status;
    if (status === 429) {
        return 'Slow down, try again in a moment';
    }
    if (status === 400) {
        return 'That track has already finished';
    }

    return "Couldn't save your rating.";
}

export function useRating(options: {
    code: string;
    state: MaybeRefOrGetter<LegacyPartyState>;
    myRating: MaybeRefOrGetter<MyRating | null | undefined>;
    onError: (message: string) => void;
}) {
    const current = computed(() => toValue(options.state).current);
    const songId = computed(() => current.value?.id ?? null);
    const own = ref<{songId: number; value: RatingValue} | null>(null);
    const adjustment = ref(0);
    let inflight: AbortController | null = null;
    let snapshot: {own: typeof own.value; adjustment: number} | null = null;
    let epoch = 0;

    const serverValue = computed<RatingValue>(() => {
        const mine = toValue(options.myRating);

        return mine && mine.playedSongId === songId.value ? mine.value : 0;
    });
    const pressed = computed<RatingValue>(() =>
        own.value !== null && own.value.songId === songId.value ? own.value.value : serverValue.value,
    );
    const score = computed(() => (current.value?.rating ?? 0) + adjustment.value);

    function settle(): void {
        inflight = null;
        snapshot = null;
    }

    watch(
        () => current.value?.rating,
        () => {
            epoch++;
            adjustment.value = 0;
        },
    );
    watch(songId, () => {
        inflight?.abort();
        settle();
        epoch++;
        adjustment.value = 0;
    });
    watch(
        () => toValue(options.myRating),
        () => {
            own.value = null;
        },
    );

    async function rate(value: RatingValue): Promise<void> {
        const id = songId.value;
        if (id === null) {
            return;
        }
        const before = pressed.value;
        snapshot ??= {own: own.value, adjustment: adjustment.value};
        const rollbackEpoch = epoch;
        inflight?.abort();
        const controller = new AbortController();
        inflight = controller;
        own.value = {songId: id, value};
        adjustment.value += value - before;

        const route = storeRating({party: options.code, playedsong: id});
        try {
            await axios.request({
                url: route.url,
                method: route.method,
                data: {rating: value},
                signal: controller.signal,
            });
            if (inflight === controller) {
                settle();
            }
        } catch (error) {
            if (inflight !== controller) {
                return;
            }
            own.value = snapshot?.own ?? null;
            adjustment.value = epoch === rollbackEpoch ? (snapshot?.adjustment ?? 0) : 0;
            settle();
            options.onError(ratingErrorMessage(error));
        }
    }

    onBeforeUnmount(() => inflight?.abort());

    return {pressed, score, rate};
}
