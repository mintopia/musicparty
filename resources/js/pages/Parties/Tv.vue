<script setup lang="ts">
import {router} from '@inertiajs/vue3';
import {ref, watch} from 'vue';
import TvPlayer from '@/components/TvPlayer.vue';
import {useEcho} from '@/composables/useEcho';
import BareLayout from '@/layouts/BareLayout.vue';
import type {LegacyTvPartyState} from '@/types';

const props = defineProps<{
    party: {code: string; name: string; url: string};
    state: LegacyTvPartyState;
}>();

const state = ref<LegacyTvPartyState>(props.state);

watch(
    () => props.state,
    (next) => {
        state.value = next;
    },
);

const {publicChannel, onReconnect} = useEcho();

(
    publicChannel(`party.${props.party.code}`) as {
        listen(event: string, cb: (payload: LegacyTvPartyState) => void): unknown;
    }
).listen('Party\\UpdatedEvent', (payload) => {
    state.value = payload;
});

onReconnect(() => {
    router.reload({only: ['state']});
});
</script>

<template>
    <BareLayout :title="party.name">
        <div class="vh-100 overflow-hidden m-0 p-0 bg-black">
            <TvPlayer :code="party.code" :party-url="party.url" :state="state" />
        </div>
    </BareLayout>
</template>
