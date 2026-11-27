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
    let inflight = false;
    let pending: {songId: number; value: RatingValue} | null = null;
    let unmounted = false;
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

    watch(
        () => current.value?.rating,
        () => {
            epoch++;
            adjustment.value = 0;
        },
    );
    watch(songId, () => {
        pending = null;
        snapshot = null;
        epoch++;
        adjustment.value = 0;
    });
    watch(
        () => toValue(options.myRating),
        () => {
            own.value = null;
        },
    );

    async function send(id: number, value: RatingValue): Promise<void> {
        inflight = true;
        const sentEpoch = epoch;
        const sentAdjustment = adjustment.value;
        const route = storeRating({party: options.code, playedsong: id});
        let failure: unknown = null;
        let failed = false;
        try {
            await axios.request({url: route.url, method: route.method, data: {rating: value}});
        } catch (error) {
            failed = true;
            failure = error;
        }
        inflight = false;

        const next = pending;
        pending = null;
        const sameSong = songId.value === id;
        if (!failed && sameSong) {
            snapshot = {own: {songId: id, value}, adjustment: epoch === sentEpoch ? sentAdjustment : 0};
        }
        if (unmounted || !sameSong) {
            return;
        }
        if (next !== null && next.songId === id && (failed || next.value !== value)) {
            void send(id, next.value);

            return;
        }
        if (failed) {
            own.value = snapshot?.own ?? null;
            adjustment.value = epoch === sentEpoch ? (snapshot?.adjustment ?? 0) : 0;
            snapshot = null;
            options.onError(ratingErrorMessage(failure));

            return;
        }
        snapshot = null;
    }

    function rate(value: RatingValue): void {
        const id = songId.value;
        if (id === null) {
            return;
        }
        const before = pressed.value;
        snapshot ??= {own: own.value, adjustment: adjustment.value};
        own.value = {songId: id, value};
        adjustment.value += value - before;

        if (inflight) {
            pending = {songId: id, value};

            return;
        }
        void send(id, value);
    }

    onBeforeUnmount(() => {
        unmounted = true;
        pending = null;
    });

    return {pressed, score, rate};
}
