<script setup lang="ts">
import {ref} from 'vue';
import {legacyFallbackName} from '@/lib/decoration';
import {formatMs, playRequest, sendControl, useRun} from '@/lib/dashboard';
import type {DashboardRequest} from '@/types';

const props = defineProps<{
    code: string;
    items: DashboardRequest[];
    total: number;
    queueUrl: string;
    canModerate: boolean;
}>();
const emit = defineEmits<{delete: [request: DashboardRequest]}>();

const run = useRun();
const busy = ref(false);

const sub = (request: DashboardRequest): string =>
    `${request.artists.map((a) => a.name).join(', ')} · ${request.user ?? legacyFallbackName(request) ?? 'Fallback'}`;

async function playNow(request: DashboardRequest): Promise<void> {
    busy.value = true;
    await run(() => (request.sent ? sendControl(props.code, 'next') : playRequest(props.code, request.id)));
    busy.value = false;
}
</script>

<template>
    <div class="deck-panel d-flex flex-column und-wrap" data-bs-theme="dark" data-test="up-next-deck">
        <div class="d-flex align-items-center mb-2">
            <h3 class="mb-0 flex-grow-1 text-white">Up Next</h3>
            <a :href="queueUrl" class="small text-white-50 text-nowrap" data-test="queue-link"
                >Queue of {{ total }} <i class="ti ti-chevron-right"></i
            ></a>
        </div>
        <div v-if="items.length === 0" class="text-secondary small" data-test="up-next-empty">Queue empty</div>
        <div v-else class="und">
            <div v-for="request in items" :key="request.id" class="und-row" data-test="up-next-row">
                <div class="und-art">
                    <img class="und-cover" :src="request.album.image_url" :title="request.album.name" alt="" />
                </div>
                <div class="und-text">
                    <div class="fw-medium text-truncate" :title="request.name">{{ request.name }}</div>
                    <div class="und-sub text-truncate" :title="sub(request)">{{ sub(request) }}</div>
                    <span v-if="request.sent" class="badge bg-green-lt mt-1" title="Already in Spotify's queue"
                        >Sent to Spotify</span
                    >
                </div>
                <div class="und-meta und-num">{{ formatMs(request.length) }}<br />{{ request.score }} votes</div>
                <div class="und-acts">
                    <button
                        type="button"
                        class="btn und-btn"
                        :title="
                            request.sent
                                ? 'Next track: skip to this now'
                                : 'Play now: move to the top of the queue and play'
                        "
                        :aria-label="request.sent ? 'Next track' : 'Play now'"
                        :disabled="busy || (!request.sent && !canModerate)"
                        data-test="play-now"
                        @click="playNow(request)"
                    >
                        <i class="ti ti-player-track-next"></i>
                    </button>
                    <span
                        v-if="request.sent"
                        class="und-slot d-inline-block"
                        tabindex="0"
                        title="Already sent to Spotify, so it can't be removed"
                        data-test="delete-sent-tip"
                    >
                        <button
                            type="button"
                            class="btn und-btn"
                            disabled
                            aria-label="Delete request (already sent to Spotify)"
                            data-test="delete"
                        >
                            <i class="ti ti-trash"></i>
                        </button>
                    </span>
                    <button
                        v-else
                        type="button"
                        class="btn und-btn"
                        :title="canModerate ? 'Delete request' : 'Only moderators can delete requests'"
                        aria-label="Delete request"
                        :disabled="!canModerate"
                        data-test="delete"
                        @click="emit('delete', request)"
                    >
                        <i class="ti ti-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.deck-panel {
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.16);
    border-radius: 12px;
    padding: 16px 18px;
    color: #fff;
    height: 100%;
    container-type: inline-size;
}
.deck-panel .text-secondary {
    color: rgba(255, 255, 255, 0.68) !important;
}
.und {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto auto;
    row-gap: 2px;
}
.und-row {
    display: grid;
    grid-column: 1 / -1;
    grid-template-columns: subgrid;
    align-items: center;
    column-gap: 12px;
    padding: 8px 4px 8px 8px;
    border-radius: 10px;
    background: transparent;
    border: 0;
}
.und-row + .und-row {
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 0;
}
.und-row:hover {
    background: rgba(255, 255, 255, 0.05);
}
.und-art {
    grid-column: 1;
    line-height: 0;
}
.und-cover {
    width: 44px;
    height: 44px;
    border-radius: 4px;
    object-fit: cover;
}
.und-text {
    grid-column: 2;
    min-width: 0;
    line-height: 1.3;
}
.und-sub {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.62);
}
.und-meta {
    grid-column: 3;
    text-align: right;
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.62);
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
    line-height: 1.5;
}
.und-acts {
    grid-column: 4;
    display: flex;
    gap: 2px;
}
.und-btn,
.und-slot {
    width: 40px;
    height: 40px;
    flex: 0 0 auto;
}
.und-slot .und-btn {
    display: block;
}
.und-btn {
    padding: 0;
    border-radius: 50%;
    border: 0;
    background: transparent;
    color: rgba(255, 255, 255, 0.6);
}
.und-btn:hover {
    background: rgba(255, 255, 255, 0.12);
    color: #fff;
}
.und-btn:disabled {
    opacity: 1;
    color: rgba(255, 255, 255, 0.22);
    background: transparent;
    border: 0;
    box-shadow: none;
}
.und-btn .ti {
    font-size: 1.25rem;
}
@container (max-width: 400px) {
    .und-num {
        display: none;
    }
}
</style>
