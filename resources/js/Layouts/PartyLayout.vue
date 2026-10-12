<script setup>
import AppShell from './AppShell.vue';
import NowPlayingBanner from '../Components/NowPlayingBanner.vue';
import {router, usePage} from '@inertiajs/vue3';
import {computed, onBeforeUnmount, ref, watch} from 'vue';

const KNOWN_PAYLOAD_VERSION = 1;

const page = usePage();
const context = computed(() => page.props.partyContext ?? null);

const liveState = ref(context.value?.party?.state ?? null);
const liveNowPlaying = ref(context.value?.nowPlaying ?? null);
const myRatings = ref({});

watch(context, (next) => {
    liveState.value = next?.party?.state ?? null;
    liveNowPlaying.value = next?.nowPlaying ?? null;
});

const code = computed(() => context.value?.party?.code ?? null);
const memberId = computed(() => page.props.membership?.id ?? null);
const paused = computed(() => liveState.value === 'paused' || liveNowPlaying.value?.paused === true);
const readOnly = computed(() => liveState.value === 'ended' || page.props.readOnly === true);

const ratablePlay = computed(() => {
    const playing = liveNowPlaying.value;
    if (!playing?.play_id) {
        return null;
    }
    const fromPage = page.props.ratablePlay?.id === playing.play_id ? page.props.ratablePlay : null;

    return {
        id: playing.play_id,
        track: playing.track,
        likes: playing.likes ?? fromPage?.likes ?? 0,
        dislikes: playing.dislikes ?? fromPage?.dislikes ?? 0,
        my_rating: myRatings.value[playing.play_id] ?? fromPage?.my_rating ?? 0,
    };
});

const applyQueueUpdate = (payload) => {
    if (payload.version > KNOWN_PAYLOAD_VERSION) {
        router.reload({only: ['partyContext'], preserveScroll: true, async: true});

        return;
    }
    const playing = payload.now_playing;
    liveNowPlaying.value = playing
        ? {...playing, started_at: payload.started_at ?? null, position_ms: playing.position_ms ?? 0, paused: false}
        : null;
};

let subscribed = [];
const unsubscribe = () => {
    subscribed.forEach((name) => window.Echo?.leave(name));
    subscribed = [];
};

watch([code, memberId], ([partyCode, member]) => {
    unsubscribe();
    if (!partyCode) {
        return;
    }
    const name = `party.${partyCode}`;
    window.Echo?.channel(name)
        .listen('.queue.updated', applyQueueUpdate)
        .listen('.party.state_changed', (payload) => {
            liveState.value = payload.state;
        });
    subscribed.push(name);
    if (member) {
        const memberName = `${name}.member.${member}`;
        window.Echo?.private(memberName).listen('.member.rating_changed', (payload) => {
            myRatings.value = {...myRatings.value, [payload.play_id]: payload.value};
        });
        subscribed.push(memberName);
    }
}, {immediate: true});

onBeforeUnmount(unsubscribe);
</script>

<template>
    <AppShell>
        <template #default="slotProps">
            <component :is="'style'" v-if="context?.themeCss" data-testid="party-theme-css">{{ context.themeCss }}</component>
            <header v-if="context" data-testid="party-header">
                <span data-testid="party-header-state" class="sr-only">{{ liveState }}</span>
                <NowPlayingBanner
                    :now-playing="liveNowPlaying"
                    :ratable-play="ratablePlay"
                    :party-code="code"
                    :read-only="readOnly"
                    :position-ms="liveNowPlaying ? (liveNowPlaying.position_ms ?? 0) : null"
                    :paused="paused"
                />
            </header>
            <slot v-bind="slotProps" />
        </template>
    </AppShell>
</template>
