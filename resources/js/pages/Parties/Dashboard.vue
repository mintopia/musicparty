<script setup lang="ts">
import {Head} from '@inertiajs/vue3';
import {computed, onBeforeUnmount, provide, ref, watch} from 'vue';
import ControlRoom from '@/components/Dashboard/ControlRoom.vue';
import DeleteRequestDialog from '@/components/Dashboard/DeleteRequestDialog.vue';
import NeedsAttention from '@/components/Dashboard/NeedsAttention.vue';
import QuickLinks from '@/components/Dashboard/QuickLinks.vue';
import UpcomingList from '@/components/Dashboard/UpcomingList.vue';
import UpNextCard from '@/components/Dashboard/UpNextCard.vue';
import {useDashboardSync} from '@/composables/useDashboardSync';
import {notifyKey} from '@/lib/dashboard';
import AppLayout from '@/layouts/AppLayout.vue';
import {home} from '@/routes';
import {dashboard, show} from '@/routes/parties';
import type {DashboardProps, DashboardRequest, LegacyPartyState} from '@/types';

const props = defineProps<DashboardProps>();

const state = ref<LegacyPartyState>(props.playerState);
const deleting = ref<DashboardRequest | null>(null);
const notice = ref<string | null>(null);
let noticeTimer: ReturnType<typeof setTimeout> | null = null;

watch(
    () => props.playerState,
    (next) => {
        state.value = next;
    },
);

function notify(message: string): void {
    notice.value = message;
    if (noticeTimer !== null) {
        clearTimeout(noticeTimer);
    }
    noticeTimer = setTimeout(() => {
        notice.value = null;
        noticeTimer = null;
    }, 5000);
}

provide(notifyKey, notify);
useDashboardSync(props.party.code, (next) => {
    state.value = next;
});

const hidden = computed(() => props.control.start.state === 'hidden');
const upcomingItems = computed(() => (hidden.value ? props.upcoming.items : [...props.upNext.slice(1), ...props.upcoming.items]));

onBeforeUnmount(() => {
    if (noticeTimer !== null) {
        clearTimeout(noticeTimer);
    }
});
</script>

<template>
    <AppLayout :title="`Dashboard - ${party.name}`">
        <template #breadcrumbs>
            <li class="breadcrumb-item"><a :href="home().url">Home</a></li>
            <li class="breadcrumb-item">
                <a :href="show(party.code).url">{{ party.name }}</a>
            </li>
            <li class="breadcrumb-item active">
                <a :href="dashboard(party.code).url">Dashboard</a>
            </li>
        </template>
        <template #header>
            <ControlRoom
                :code="party.code"
                :host="host.name"
                :state="state"
                :control="control"
                :up-next="upNext"
                :total="upcoming.total"
                :queue-url="links.songs"
                :can-control="can.control"
                :can-moderate="can.moderate"
                @delete="deleting = $event"
            />
        </template>
        <Head :title="`Dashboard - ${party.name}`" />
        <div v-if="notice" class="position-fixed z-3 p-2 top-50 start-50 translate-middle" data-test="notice">
            <div class="alert alert-important alert-danger" role="alert">
                <div class="d-flex">
                    <div><i class="icon ti ti-exclamation-circle"></i></div>
                    <div class="px-2">{{ notice }}</div>
                </div>
            </div>
        </div>
        <div class="row g-4">
            <div class="col-lg-7">
                <UpNextCard v-if="!hidden && upNext.length > 0" :request="upNext[0]" />
                <UpcomingList
                    :code="party.code"
                    :items="upcomingItems"
                    :total="upcoming.total"
                    :queue-url="links.songs"
                    :fallback-settings-url="links.fallbackSettings"
                    :has-fallback-playlist="hasFallbackPlaylist"
                    :can-moderate="can.moderate"
                    :can-downvote="party.downvotes"
                    :timezone="timezone"
                    @delete="deleting = $event"
                />
            </div>
            <div class="col-lg-5">
                <NeedsAttention
                    :code="party.code"
                    :issues="attention.issues"
                    :flagged="attention.flagged"
                    :flagged-total="attention.flaggedTotal"
                    :flagged-url="links.flagged"
                    :can-moderate="can.moderate"
                />
                <QuickLinks :links="links" />
            </div>
        </div>
        <DeleteRequestDialog :code="party.code" :request="deleting" @close="deleting = null" />
    </AppLayout>
</template>
