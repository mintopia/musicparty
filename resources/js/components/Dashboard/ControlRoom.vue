<script setup lang="ts">
import {computed} from 'vue';
import PlayerUnit from '@/components/Dashboard/PlayerUnit.vue';
import StartPlaybackPanel from '@/components/Dashboard/StartPlaybackPanel.vue';
import UpNextDeck from '@/components/Dashboard/UpNextDeck.vue';
import type {DashboardControl, DashboardRequest, LegacyPartyState} from '@/types';

const props = defineProps<{
    code: string;
    host: string;
    state: LegacyPartyState;
    control: DashboardControl;
    upNext: DashboardRequest[];
    total: number;
    queueUrl: string;
    canControl: boolean;
    canModerate: boolean;
}>();
const emit = defineEmits<{delete: [request: DashboardRequest]}>();

const hidden = computed(() => props.control.start.state === 'hidden');
const bannerStyle = computed(() => {
    const image = props.state.now?.album?.image_url;
    return image ? `background-image: url('${image}');` : '';
});
</script>

<template>
    <div class="player p-0" data-test="control-room">
        <div class="blur-bg py-4 border-bottom border-primary text-white" :style="bannerStyle">
            <div class="container-fluid px-4">
                <div class="row g-4 align-items-stretch">
                    <div class="col-xl-7 col-lg-6">
                        <PlayerUnit
                            :code="code"
                            :host="host"
                            :state="state"
                            :control="control"
                            :can-control="canControl"
                        />
                    </div>
                    <div class="col-xl-5 col-lg-6">
                        <UpNextDeck
                            v-if="hidden"
                            :code="code"
                            :items="upNext"
                            :total="total"
                            :queue-url="queueUrl"
                            :can-moderate="canModerate"
                            @delete="emit('delete', $event)"
                        />
                        <StartPlaybackPanel
                            v-else
                            :code="code"
                            :host="host"
                            :start="control.start"
                            :playing-from-label="control.playingFrom.label"
                            :can-control="canControl"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.player {
    overflow: hidden;
}
.blur-bg {
    backdrop-filter: blur(2px);
    background-repeat: no-repeat;
    background-size: 100%;
    background-position: center center;
    background-blend-mode: darken;
    background-color: rgba(0, 0, 0, 0.9);
}
</style>
