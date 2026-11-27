<script setup lang="ts">
import {ref, watch} from 'vue';
import {useEcho} from '@/composables/useEcho';
import {formatMs} from '@/lib/dashboard';
import {legacyAccentStyle, legacyFallbackName, legacyRowClasses} from '@/lib/decoration';
import type {LegacyPartyState} from '@/types';

const props = defineProps<{code: string; initialState: LegacyPartyState}>();

const state = ref<LegacyPartyState>(props.initialState);

watch(
    () => props.initialState,
    (next) => {
        state.value = next;
    },
);

(
    useEcho().publicChannel(`party.${props.code}`) as {
        listen(event: string, cb: (payload: LegacyPartyState) => void): void;
    }
).listen('Party\\UpdatedEvent', (payload) => {
    state.value = payload;
});
</script>
<template>
    <div>
        <template v-if="state !== null && state.next">
            <h3>Up Next</h3>

            <div class="card mb-3">
                <div class="list-group list-group-flush">
                    <div class="list-group-item">
                        <div
                            :class="legacyRowClasses(state.next)"
                            :style="legacyAccentStyle(state.next)"
                            data-test="track-row"
                        >
                            <div class="col-auto">
                                <img
                                    :src="state.next.album.image_url"
                                    :title="state.next.album.name"
                                    :alt="state.next.album.name"
                                    class="rounded"
                                    width="60"
                                    height="60"
                                    loading="lazy"
                                    decoding="async"
                                />
                            </div>
                            <div class="col">
                                {{ state.next.name }}
                                <span
                                    v-if="state.next.decoration?.label"
                                    class="badge bg-azure-lt ms-1"
                                    data-test="decoration-label"
                                    >{{ state.next.decoration.label }}</span
                                >
                                <div class="text-secondary">
                                    {{ state.next.artists.map((artist) => artist.name).join(', ') }}
                                </div>
                                <div class="text-muted">
                                    <template v-if="state.next.user">Requested by {{ state.next.user }}</template>
                                    <template v-else>{{ legacyFallbackName(state.next) }}</template>
                                </div>
                            </div>
                            <div class="col-auto text-secondary text-center">
                                {{ formatMs(state.next.length) }}
                                <div class="text-muted mt-2" data-test="score">
                                    {{ state.next.score }}
                                    {{ Math.abs(state.next.score) === 1 ? 'vote' : 'votes' }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>

<style>
.whamageddon {
    padding-top: 20px;
}

.whamageddon::before {
    content: 'Whamageddon!';
    white-space: nowrap;
    position: absolute;
    padding: 5px 100px;
    width: 100%;
    color: white;
    text-align: center;
    text-transform: uppercase;
    font-size: 10px;
    font-weight: bold;
    box-sizing: border-box;
    top: 0;
    left: 0;
    background-color: #990000;
}
</style>
