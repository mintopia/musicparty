<script setup lang="ts">
import {ref, watch} from 'vue';
import {useEcho} from '@/composables/useEcho';
import TrackRow from '@/components/TrackRow.vue';
import {useIsPhone} from '@/composables/useIsPhone';
import {legacyFallbackLabel, legacyToAnnotation} from '@/lib/legacyTrack';
import type {LegacyPartyState} from '@/types';

const props = defineProps<{code: string; initialState: LegacyPartyState}>();

const isPhone = useIsPhone();
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
                    <TrackRow
                        :track="legacyToAnnotation(state.next)"
                        :compact="isPhone"
                        :fallback-name="legacyFallbackLabel(state.next)"
                        score-only
                    />
                </div>
            </div>
        </template>
    </div>
</template>
