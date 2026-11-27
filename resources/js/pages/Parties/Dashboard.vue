<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import {partyCrumbs} from '@/lib/breadcrumbs';
import Toast from '@/components/Toast.vue';
import {Head} from '@inertiajs/vue3';
import {computed, onBeforeUnmount, provide, ref, watch} from 'vue';
import ControlRoom from '@/components/Dashboard/ControlRoom.vue';
import DeleteRequestDialog from '@/components/Dashboard/DeleteRequestDialog.vue';
import NeedsAttention from '@/components/Dashboard/NeedsAttention.vue';
import QuickLinks from '@/components/Dashboard/QuickLinks.vue';
import UpcomingList from '@/components/Dashboard/UpcomingList.vue';
import {useDashboardSync} from '@/composables/useDashboardSync';
import {notifyKey} from '@/lib/dashboard';
import AppLayout from '@/layouts/AppLayout.vue';
import type {DashboardProps, DashboardRequest, LegacyPartyState} from '@/types';

const props = defineProps<DashboardProps>();

const state = ref<LegacyPartyState>(props.playerState);
const issues = computed(() =>
    props.attention.issues.filter(
        (issue) => issue.code !== 'player_stale' || props.control.health.player.connected !== true,
    ),
);
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
const {connected} = useDashboardSync(props.party.code, (next) => {
    state.value = next;
});

onBeforeUnmount(() => {
    if (noticeTimer !== null) {
        clearTimeout(noticeTimer);
    }
});
</script>

<template>
    <AppLayout :title="`Dashboard - ${party.name}`">
        <template #breadcrumbs>
            <Breadcrumbs :items="[...partyCrumbs(party), {label: 'Dashboard'}]" />
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
                :can-start="party.canManage"
                :my-rating="myRating"
                :connected="connected"
                @delete="deleting = $event"
            />
        </template>
        <Head :title="`Dashboard - ${party.name}`" />
        <Toast v-if="notice" :message="notice" variant="danger" data-test="notice" @dismiss="notice = null" />
        <div class="row g-4">
            <div class="col-lg-7">
                <UpcomingList
                    :code="party.code"
                    :items="upcoming.items"
                    :total="upcoming.total"
                    :queue-url="links.songs"
                    :fallback-settings-url="links.fallbackSettings"
                    :can-manage="party.canManage"
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
                    :issues="issues"
                    :flagged="attention.flagged"
                    :flagged-total="attention.flaggedTotal"
                    :flagged-url="links.flagged"
                    :can-moderate="can.moderate"
                />
                <QuickLinks :links="links" :can-manage="party.canManage" />
            </div>
        </div>
        <DeleteRequestDialog :code="party.code" :request="deleting" @close="deleting = null" />
    </AppLayout>
</template>
