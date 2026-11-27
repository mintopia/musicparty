<script setup lang="ts">
import {computed} from 'vue';
import NowPlayingBackdrop from '@/components/NowPlayingBackdrop.vue';
import PlayerUnit from '@/components/Dashboard/PlayerUnit.vue';
import StartPlaybackPanel from '@/components/Dashboard/StartPlaybackPanel.vue';
import UpNextDeck from '@/components/Dashboard/UpNextDeck.vue';
import type {PlaybackStatus} from '@/lib/playbackStatus';
import type {DashboardControl, DashboardProps, DashboardRequest, LegacyPartyState, MyRating} from '@/types';

const props = withDefaults(
    defineProps<{
        code: string;
        partyName: string;
        host: string;
        state: LegacyPartyState;
        control: DashboardControl;
        status: PlaybackStatus;
        links: DashboardProps['links'];
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

const showDeck = computed(
    () =>
        ['playing', 'paused'].includes(props.status.kind) &&
        !['relink_required', 'premium_required'].includes(props.control.start.state),
);
const bannerImage = computed(() => props.state.now?.album?.image_url ?? null);
</script>

<template>
    <div data-test="control-room">
        <NowPlayingBackdrop :image="bannerImage" class="py-4 border-bottom border-primary">
            <div class="container-fluid px-4">
                <div class="band-head" data-test="band-head">
                    <h1><b>Dashboard</b> &middot; {{ partyName }}</h1>
                    <span class="code" data-test="band-code"
                        >Join code <b>{{ code }}</b></span
                    >
                </div>
                <div class="row g-4 align-items-stretch">
                    <div class="col-xl-7 col-lg-6">
                        <PlayerUnit
                            :code="code"
                            :state="state"
                            :control="control"
                            :status="status"
                            :web-player-url="links.webPlayer"
                            :relink-url="links.relink"
                            :can-control="canControl"
                            :my-rating="myRating"
                            :connected="connected"
                        />
                    </div>
                    <div class="col-xl-5 col-lg-6">
                        <UpNextDeck
                            v-if="showDeck"
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
                            :status="status"
                            :web-player-url="links.webPlayer"
                            :relink-url="links.relink"
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

<style scoped>
.band-head {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 16px;
    margin: 0 0 22px;
}
.band-head h1 {
    margin: 0;
    font-size: 1rem;
    font-weight: 500;
    color: var(--mp-deck-muted);
    letter-spacing: 0;
}
.band-head h1 b {
    color: var(--mp-deck-text);
    font-weight: 650;
}
.band-head .code {
    font-size: 0.8125rem;
    color: var(--mp-deck-muted);
    white-space: nowrap;
}
.band-head .code b {
    color: var(--mp-deck-text);
    letter-spacing: 0.1em;
}
</style>
