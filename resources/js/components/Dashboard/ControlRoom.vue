<script setup lang="ts">
import {computed} from 'vue';
import NowPlayingBackdrop from '@/components/NowPlayingBackdrop.vue';
import PlayerUnit from '@/components/Dashboard/PlayerUnit.vue';
import StartPlaybackPanel from '@/components/Dashboard/StartPlaybackPanel.vue';
import UpNextDeck from '@/components/Dashboard/UpNextDeck.vue';
import type {DashboardControl, DashboardRequest, LegacyPartyState, MyRating} from '@/types';

const props = withDefaults(
    defineProps<{
        code: string;
        host: string;
        state: LegacyPartyState;
        control: DashboardControl;
        upNext: DashboardRequest[];
        total: number;
        queueUrl: string;
        canControl: boolean;
        canModerate: boolean;
        canStart?: boolean;
        myRating?: MyRating | null;
        connected?: boolean;
    }>(),
    {myRating: null, connected: true, canStart: true},
);
const emit = defineEmits<{delete: [request: DashboardRequest]}>();

const hidden = computed(() => props.control.start.state === 'hidden');
const bannerImage = computed(() => props.state.now?.album?.image_url ?? null);
</script>

<template>
    <div data-test="control-room">
        <NowPlayingBackdrop :image="bannerImage" class="py-4 border-bottom border-primary">
            <div class="container-fluid px-4">
                <div class="row g-4 align-items-stretch">
                    <div class="col-xl-7 col-lg-6">
                        <PlayerUnit
                            :code="code"
                            :state="state"
                            :control="control"
                            :can-control="canControl"
                            :my-rating="myRating"
                            :connected="connected"
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
                            :can-start="canStart"
                        />
                    </div>
                </div>
            </div>
        </NowPlayingBackdrop>
    </div>
</template>
