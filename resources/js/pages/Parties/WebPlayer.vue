<script setup lang="ts">
import NowPlayingBackdrop from '@/components/NowPlayingBackdrop.vue';
import PlayerStatusPill from '@/components/PlayerStatusPill.vue';
import WebPlayerStatusCard from '@/components/WebPlayerStatusCard.vue';
import WebPlayerTransport from '@/components/WebPlayerTransport.vue';
import {useWebPlayer} from '@/composables/useWebPlayer';
import {useWebPlayerChecks} from '@/composables/useWebPlayerChecks';
import BareLayout from '@/layouts/BareLayout.vue';

const props = defineProps<{
    party: {code: string; name: string};
    tokenUrl: string;
    activateUrl: string;
    deviceName: string;
}>();

const player = useWebPlayer({
    partyCode: props.party.code,
    tokenUrl: props.tokenUrl,
    activateUrl: props.activateUrl,
    deviceName: props.deviceName,
});
const {
    error,
    errorKind,
    track,
    paused,
    duration,
    autoplayBlocked,
    takenOver,
    active,
    deviceReady,
    artists,
    art,
    livePosition,
    seekPercent,
    showTransport,
    command,
    useThisTab,
    startPlayback,
    resumePlayback,
    seekTo,
} = player;
const {errorTitle, errorCopy, errorIcon, spotifyCheck, checks, pillLabel, pillWarn} = useWebPlayerChecks(
    player,
    props.deviceName,
);
</script>

<template>
    <BareLayout :title="`Web player - ${party.name}`">
        <div class="wp">
            <NowPlayingBackdrop :image="art" class="wp-hero">
                <div class="wp-wrap">
                    <div class="wp-top">
                        <h1><i class="ti ti-speakerphone" aria-hidden="true"></i>{{ party.name }} web player</h1>
                        <div role="status" aria-live="polite" data-test="status">
                            <PlayerStatusPill :warn="pillWarn">{{ pillLabel }}</PlayerStatusPill>
                        </div>
                    </div>
                    <div class="wp-main">
                        <img v-if="track && art && !takenOver && !error" class="wp-art" :src="art" alt="" />
                        <div v-else class="wp-art wp-art-blank">
                            <i
                                class="ti"
                                :class="takenOver ? 'ti-switch-horizontal' : error ? errorIcon : 'ti-music'"
                                aria-hidden="true"
                            ></i>
                        </div>
                        <div v-if="takenOver" data-test="taken-over">
                            <h2 class="wp-title">Another player took over</h2>
                            <p class="wp-copy">
                                Another Web Player is now playing for {{ party.name }}. This tab has stopped, so the
                                party isn't heard twice.
                            </p>
                            <div class="wp-action">
                                <button
                                    type="button"
                                    class="btn btn-light btn-lg"
                                    data-test="use-this-tab"
                                    @click="useThisTab"
                                >
                                    <i class="icon ti ti-player-play me-1"></i>Use this tab instead
                                </button>
                            </div>
                        </div>
                        <div v-else-if="error">
                            <h2 class="wp-title">{{ errorTitle }}</h2>
                            <p class="wp-copy" data-test="error">{{ errorCopy }}</p>
                            <div v-if="spotifyCheck.action" class="wp-action">
                                <a
                                    :href="spotifyCheck.action.href"
                                    class="btn btn-light btn-lg"
                                    data-test="error-action"
                                >
                                    <i
                                        class="icon ti me-1"
                                        :class="errorKind === 'login' ? 'ti-brand-spotify' : 'ti-external-link'"
                                    ></i
                                    >{{ spotifyCheck.action.label }}
                                </a>
                            </div>
                        </div>
                        <div v-else-if="track" data-test="now-playing">
                            <h2 class="wp-title" data-test="track-name">{{ track.name }}</h2>
                            <p class="wp-artists" data-test="track-artists">{{ artists }}</p>
                            <p v-if="track.album?.name" class="wp-album" data-test="track-album">
                                {{ track.album.name }}
                            </p>
                            <span class="visually-hidden" data-test="play-state">{{
                                paused ? 'Paused' : 'Playing'
                            }}</span>
                        </div>
                        <div v-else>
                            <h2 class="wp-title">{{ active ? 'Ready to play' : 'Connecting' }}</h2>
                            <p class="wp-copy">Press play to start the party's music in this tab.</p>
                        </div>
                    </div>
                    <template v-if="showTransport">
                        <WebPlayerTransport
                            :autoplay-blocked="autoplayBlocked"
                            :paused="paused"
                            :duration="duration"
                            :live-position="livePosition"
                            :seek-percent="seekPercent"
                            @resume="resumePlayback"
                            @seek="seekTo"
                            @previous="command((sdk) => sdk.previousTrack())"
                            @toggle="startPlayback"
                            @next="command((sdk) => sdk.nextTrack())"
                        />
                    </template>
                </div>
            </NowPlayingBackdrop>
            <main class="wp-body">
                <div class="wp-wrap">
                    <WebPlayerStatusCard :checks="checks" :show-leave-notice="!takenOver && deviceReady" />
                </div>
            </main>
        </div>
    </BareLayout>
</template>
