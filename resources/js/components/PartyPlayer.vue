<script setup lang="ts">
import {router} from '@inertiajs/vue3';
import {computed, onBeforeUnmount, ref, watch} from 'vue';
import NowPlayingBackdrop from '@/components/NowPlayingBackdrop.vue';
import PartyPlayerNext from '@/components/PartyPlayerNext.vue';
import PartyPlayerNow from '@/components/PartyPlayerNow.vue';
import PlayerStatusPill from '@/components/PlayerStatusPill.vue';
import Toast from '@/components/Toast.vue';
import {useEcho} from '@/composables/useEcho';
import {useStatusProgress} from '@/composables/useProgress';
import {useRating} from '@/composables/useRating';
import type {LegacyPartyState, MyRating} from '@/types';

const props = defineProps<{code: string; initialState: LegacyPartyState; myRating?: MyRating | null}>();

const state = ref<LegacyPartyState>(props.initialState);
const {hasLength, paused, progressPercent, elapsedLabel, totalLabel} = useStatusProgress(() => state.value.status);
const backgroundImage = computed(() => state.value.now?.album?.image_url ?? null);

const notice = ref<string | null>(null);
let noticeTimer: ReturnType<typeof setTimeout> | null = null;

function clearNotice(): void {
    notice.value = null;
    if (noticeTimer !== null) {
        clearTimeout(noticeTimer);
        noticeTimer = null;
    }
}

function notify(message: string): void {
    clearNotice();
    notice.value = message;
    noticeTimer = setTimeout(clearNotice, 4000);
}

const {pressed, score, rate} = useRating({
    code: props.code,
    state,
    myRating: () => props.myRating,
    onError: notify,
});

watch(
    () => props.initialState,
    (next) => {
        state.value = next;
    },
);

const echo = useEcho();
const connected = echo.trackConnection();
(
    echo.publicChannel(`party.${props.code}`) as {
        listen(event: string, cb: (payload: LegacyPartyState) => void): void;
    }
).listen('Party\\UpdatedEvent', (payload) => {
    state.value = payload;
});
echo.onReconnect(() => router.reload({only: ['playerState', 'myRating']}));

onBeforeUnmount(clearNotice);
</script>

<template>
    <div data-test="party-player">
        <NowPlayingBackdrop :image="backgroundImage" class="player-band border-bottom border-primary">
            <div class="container-xl">
                <div class="col-xl-8 offset-xl-2 col-lg-10 offset-lg-1 player-frame">
                    <div class="player-status" role="status" aria-live="polite">
                        <PlayerStatusPill v-if="!connected" warn data-test="reconnecting"
                            >Reconnecting…</PlayerStatusPill
                        >
                        <PlayerStatusPill v-else-if="paused && state.now !== null" data-test="paused">
                            Paused
                        </PlayerStatusPill>
                    </div>

                    <PartyPlayerNow
                        v-if="state.now !== null"
                        :state="state"
                        :connected="connected"
                        :paused="paused"
                        :has-length="hasLength"
                        :progress-percent="progressPercent"
                        :elapsed-label="elapsedLabel"
                        :total-label="totalLabel"
                        :score="score"
                        :pressed="pressed"
                        @rate="rate"
                    />
                    <div v-else class="player-empty" data-test="nothing-playing">
                        <i class="ti ti-music-off player-empty-icon" aria-hidden="true"></i>
                        <div class="player-empty-title">Nothing playing</div>
                        <p>Search for a track to get the party started.</p>
                    </div>

                    <PartyPlayerNext :next="state.next" />
                </div>
            </div>
        </NowPlayingBackdrop>
        <Toast v-if="notice" :message="notice" variant="danger" data-test="rating-notice" @dismiss="clearNotice" />
    </div>
</template>

<style scoped>
.player-frame {
    --art: 168px;
    position: relative;
    font-family: var(--mp-font-family, inherit);
}

.player-status {
    position: absolute;
    inset-block-start: 12px;
    inset-inline-end: 14px;
    z-index: 2;
}

.player-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-block-size: calc(var(--art) + 36px);
    padding: 20px;
    text-align: center;
}

.player-empty-icon {
    font-size: 40px;
    color: rgba(255, 255, 255, 0.55);
}

.player-empty-title {
    margin: 8px 0 0;
    font-size: 20px;
    font-weight: 650;
    color: #fff;
}

.player-empty p {
    margin: 6px 0 0;
    font-size: 14px;
    color: rgba(255, 255, 255, 0.7);
}

@media (max-width: 575px) {
    .player-frame {
        --art: 84px;
    }

    .player-status {
        inset-block-start: 6px;
        inset-inline-end: 8px;
    }
}</style>
