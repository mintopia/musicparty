<script setup lang="ts">
import {Head, router} from '@inertiajs/vue3';
import {onBeforeUnmount, onMounted, ref} from 'vue';
import Next from '@/components/Next.vue';
import PartyPlayer from '@/components/PartyPlayer.vue';
import Upcoming from '@/components/Upcoming.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {home} from '@/routes';
import {ping} from '@/routes/api/v1';
import {show} from '@/routes/parties';
import type {LegacyPartyState, LegacyUpcomingSong} from '@/types';

const props = defineProps<{
    party: {code: string; name: string; downvotes: boolean; canManage: boolean; weighted: boolean};
    playerState: LegacyPartyState;
    upcoming: LegacyUpcomingSong[];
    timezone: string;
    spotifyNeedsRelink: boolean;
    relinkUrl: string;
    searchUrl: string;
}>();

const query = ref('');
let keepalive: ReturnType<typeof setInterval> | null = null;

function submit(): void {
    router.get(props.searchUrl, {query: query.value});
}

onMounted(() => {
    keepalive = setInterval(() => {
        void fetch(ping().url);
    }, 60000);
});

onBeforeUnmount(() => {
    if (keepalive !== null) {
        clearInterval(keepalive);
    }
});
</script>

<template>
    <AppLayout :title="party.name">
        <template #breadcrumbs>
            <li class="breadcrumb-item"><a :href="home().url">Home</a></li>
            <li class="breadcrumb-item active">
                <a :href="show(party.code).url">{{ party.name }}</a>
            </li>
        </template>
        <template #header>
            <PartyPlayer :code="party.code" :initial-state="playerState" />
        </template>
        <Head :title="party.name" />
        <div v-if="spotifyNeedsRelink" class="col-xl-8 offset-xl-2 col-lg-10 offset-lg-1">
            <div id="spotify-relink-banner" class="alert alert-warning" role="alert">
                Spotify needs re-linking.
                <a :href="relinkUrl">Re-link your Spotify account</a>
                to resume playback updates.
            </div>
        </div>
        <div class="col-xl-8 offset-xl-2 col-lg-10 offset-lg-1">
            <Next :code="party.code" :initial-state="playerState" />
            <div class="d-flex flex-fill align-text-bottom mb-1">
                <div class="flex-grow-1">
                    <h3 class="mt-2">{{ party.weighted ? 'Upcoming Songs' : 'Queue' }}</h3>
                </div>
                <div class="text-end">
                    <form :action="searchUrl" method="get" @submit.prevent="submit">
                        <div class="d-flex">
                            <div class="flex-grow-1">
                                <div class="input-icon">
                                    <input
                                        id="query"
                                        v-model="query"
                                        type="text"
                                        class="form-control"
                                        name="query"
                                        placeholder="Search…"
                                    />
                                    <span class="input-icon-addon">
                                        <i class="icon ti ti-music-search"></i>
                                    </span>
                                </div>
                            </div>
                            <div class="flex-column ps-2">
                                <button type="submit" class="btn btn-primary">Search</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <div v-if="party.weighted" class="d-flex flex-fill align-text-bottom mt-2 mb-1">
                <p>Tracks with more upvotes are more likely to be played.</p>
            </div>
            <Upcoming
                :party="party.code"
                :can-manage="party.canManage"
                :can-downvote="party.downvotes"
                :timezone="timezone"
                :initial-state="upcoming"
            />
        </div>
    </AppLayout>
</template>
