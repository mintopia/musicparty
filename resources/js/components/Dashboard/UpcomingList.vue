<script setup lang="ts">
import axios from 'axios';
import {computed, onBeforeUnmount, onMounted, ref} from 'vue';
import ScheduleSheet from '@/components/Dashboard/ScheduleSheet.vue';
import EmptyState from '@/components/EmptyState.vue';
import VoteControl from '@/components/VoteControl.vue';
import {estimateStarts, formatMs, useRun} from '@/lib/dashboard';
import {legacyFallbackName} from '@/lib/decoration';
import {store as storeVote} from '@/routes/api/v1/parties/upcomingsongs/vote';
import type {DashboardRequest, LegacyPlaybackStatus} from '@/types';

const props = defineProps<{
    code: string;
    items: DashboardRequest[];
    total: number;
    queueUrl: string;
    fallbackSettingsUrl: string;
    canManage?: boolean;
    hasFallbackPlaylist: boolean;
    canModerate: boolean;
    canDownvote: boolean;
    timezone: string;
    status?: LegacyPlaybackStatus | null;
    nowTrackName?: string | null;
    now?: number;
}>();
const emit = defineEmits<{delete: [request: DashboardRequest]}>();

const run = useRun();
const openMenuId = ref<number | null>(null);
const scheduling = ref<DashboardRequest | null>(null);
const estimates = computed(() =>
    estimateStarts(props.items, props.status, props.nowTrackName ?? null, props.now ?? Date.now()),
);

function toggleMenu(request: DashboardRequest): void {
    openMenuId.value = openMenuId.value === request.id ? null : request.id;
}

function removeFromMenu(request: DashboardRequest): void {
    openMenuId.value = null;
    emit('delete', request);
}

function scheduleFromMenu(request: DashboardRequest): void {
    openMenuId.value = null;
    scheduling.value = request;
}

function closeMenu(event: Event): void {
    if (!(event.target as Element | null)?.closest?.('.row-menu')) {
        openMenuId.value = null;
    }
}

function closeMenuOnEscape(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        openMenuId.value = null;
    }
}

onMounted(() => {
    document.addEventListener('click', closeMenu);
    document.addEventListener('keydown', closeMenuOnEscape);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', closeMenu);
    document.removeEventListener('keydown', closeMenuOnEscape);
});

function formatClock(value: string | number): string {
    return new Intl.DateTimeFormat('en-GB', {timeStyle: 'short', timeZone: props.timezone, hourCycle: 'h23'}).format(
        new Date(value),
    );
}

function formatScheduled(iso: string): string {
    return new Intl.DateTimeFormat(undefined, {timeStyle: 'short', timeZone: props.timezone}).format(new Date(iso));
}

async function vote(request: DashboardRequest, value: number): Promise<void> {
    const route = storeVote({party: props.code, upcomingsong: request.id});
    await run(() => axios.request({url: route.url, method: route.method, data: {vote: value}}), ['upNext', 'upcoming']);
}
</script>

<template>
    <div class="upcoming-list" data-test="upcoming-list">
        <div class="hdr d-flex align-items-center mt-4 mb-2">
            <h2 class="mb-0 flex-grow-1">Upcoming Songs</h2>
            <a :href="queueUrl" data-test="manage-link"
                >Manage all {{ total }} requests <i class="ti ti-chevron-right"></i
            ></a>
        </div>
        <div class="card">
            <EmptyState v-if="items.length === 0" icon="ti-playlist-off" title="Queue empty">
                <a
                    v-if="!hasFallbackPlaylist && canManage !== false"
                    :href="fallbackSettingsUrl"
                    class="btn btn-outline-secondary"
                    data-test="set-fallback"
                    >Set Fallback Playlist</a
                >
            </EmptyState>
            <div v-else class="list-group card-list-group">
                <div
                    v-for="(request, index) in items"
                    :key="request.id"
                    class="list-group-item py-2"
                    data-test="upcoming-row"
                >
                    <div class="row align-items-center g-3 qrow" :class="{'is-sent': request.sent}">
                        <div class="col-auto pos d-none d-md-block" data-test="position">{{ index + 1 }}</div>
                        <div class="col-auto">
                            <img
                                :src="request.album.image_url"
                                :title="request.album.name"
                                class="rounded"
                                width="44"
                                height="44"
                                alt=""
                            />
                        </div>
                        <div class="col">
                            <div class="title">{{ request.name }}</div>
                            <div class="text-secondary d-none d-md-block">
                                {{ request.artists.map((a) => a.name).join(', ') }}
                            </div>
                            <div class="text-secondary by d-md-none">
                                {{ request.artists.map((a) => a.name).join(' · ') }} ·
                                <template v-if="request.user"
                                    >Requested by <b>{{ request.user }}</b></template
                                >
                                <span v-else class="credit" title="From the party's Fallback Playlist"
                                    ><i class="ti ti-playlist"></i>Fallback Playlist</span
                                >
                            </div>
                            <div class="text-muted small d-none d-md-block">
                                <template v-if="request.user">Requested by {{ request.user }}</template>
                                <template v-else>Fallback Playlist: {{ legacyFallbackName(request) }}</template>
                            </div>
                        </div>
                        <div v-if="!request.sent" class="col-auto row-menu dropdown d-md-none">
                            <button
                                type="button"
                                class="btn btn-ghost-secondary btn-icon"
                                aria-label="Request actions"
                                aria-haspopup="menu"
                                :aria-expanded="openMenuId === request.id"
                                data-test="row-menu-toggle"
                                @click="toggleMenu(request)"
                            >
                                <i class="ti ti-dots"></i>
                            </button>
                            <div
                                class="dropdown-menu dropdown-menu-end"
                                :class="{show: openMenuId === request.id}"
                                role="menu"
                                data-test="row-menu"
                            >
                                <button
                                    type="button"
                                    class="dropdown-item"
                                    role="menuitem"
                                    data-test="menu-schedule"
                                    @click="scheduleFromMenu(request)"
                                >
                                    <i class="ti ti-calendar-clock"></i>
                                    <span
                                        ><span class="d-block">{{
                                            request.scheduled_for ? 'Change schedule' : 'Schedule'
                                        }}</span
                                        ><small class="text-secondary">{{
                                            request.scheduled_for
                                                ? 'Pick a different time for it to play'
                                                : 'Pick a day and time for it to play'
                                        }}</small></span
                                    >
                                </button>
                                <button
                                    type="button"
                                    class="dropdown-item text-danger"
                                    role="menuitem"
                                    :disabled="!canModerate"
                                    data-test="menu-remove"
                                    @click="removeFromMenu(request)"
                                >
                                    <i class="ti ti-trash"></i>
                                    <span
                                        ><span class="d-block">Remove request</span
                                        ><small class="text-secondary">{{
                                            canModerate
                                                ? 'It leaves the Queue and its votes are lost'
                                                : 'Only moderators can remove requests'
                                        }}</small></span
                                    >
                                </button>
                            </div>
                        </div>
                        <div class="qbreak d-md-none"></div>
                        <div class="col-auto text-secondary qtime">
                            <span v-if="request.scheduled_for" class="plays-at" data-test="plays-at"
                                ><i class="ti ti-calendar-time"></i>Plays at
                                {{ formatScheduled(request.scheduled_for) }}</span
                            >
                            <span
                                v-if="!request.scheduled_for && estimates[index]"
                                class="est d-md-none"
                                data-test="est-start"
                                >About {{ formatClock(estimates[index]!.startsAt) }}</span
                            >
                            <span>{{ formatMs(request.length) }}</span>
                        </div>
                        <div v-if="request.sent" class="col-auto qsent" data-test="sent-badge">
                            <span class="badge bg-green-lt" title="Already handed to the Player">Sent to Player</span>
                        </div>
                        <div v-if="!request.sent" class="col-auto votes">
                            <VoteControl
                                :score="request.score"
                                :vote="request.vote"
                                :can-downvote="canDownvote"
                                :label="request.name"
                                @vote="(value) => vote(request, value)"
                            />
                        </div>
                        <div v-if="!request.sent" class="col-auto qa d-none d-md-block">
                            <button
                                type="button"
                                class="btn btn-ghost-secondary btn-icon"
                                aria-label="Schedule"
                                title="Schedule"
                                data-test="schedule"
                                @click="scheduling = request"
                            >
                                <i class="ti ti-calendar-clock"></i>
                            </button>
                            <button
                                type="button"
                                class="btn btn-ghost-danger btn-icon"
                                aria-label="Remove request"
                                :title="canModerate ? 'Remove request' : 'Only moderators can remove requests'"
                                :disabled="!canModerate"
                                data-test="remove"
                                @click="emit('delete', request)"
                            >
                                <i class="ti ti-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <ScheduleSheet
            v-if="scheduling"
            :key="scheduling.id"
            :title="`Schedule ${scheduling.name}`"
            :party="code"
            :song="scheduling.id"
            :timezone="timezone"
            :scheduled-for="scheduling.scheduled_for"
            @close="scheduling = null"
        />
    </div>
</template>

<style scoped>
.upcoming-list:first-child > .hdr {
    margin-top: 0 !important;
}
.title {
    font-weight: 500;
}
.votes {
    min-width: 88px;
}
.votes :deep(.vote-control) {
    background: var(--tblr-bg-surface-secondary);
    border-radius: 99px;
    padding: 2px 4px;
}
.pos {
    inline-size: 2.5ch;
    padding-inline: 0 !important;
    text-align: end;
    color: var(--tblr-secondary-color);
    font-size: 0.8125rem;
    font-variant-numeric: tabular-nums;
}
.qa .btn {
    width: 36px;
    height: 36px;
    padding: 0;
}
.row-menu {
    position: relative;
}
.row-menu .btn {
    width: 44px;
    height: 44px;
}
.row-menu .dropdown-menu {
    min-width: 260px;
    max-width: calc(100vw - 32px);
}
.row-menu .dropdown-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    min-height: 44px;
    white-space: normal;
}
.row-menu .dropdown-item > .ti {
    font-size: 1.25rem;
    margin-top: 2px;
}
.row-menu .dropdown-item {
    width: 100%;
    text-align: start;
}
.plays-at {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 2px 10px;
    border-radius: 99px;
    font-size: 0.8125rem;
    font-weight: 600;
    background: color-mix(in oklab, var(--tblr-yellow) 22%, var(--tblr-bg-surface));
    color: color-mix(in oklab, var(--tblr-yellow) 45%, var(--tblr-body-color));
}
.qtime .est {
    color: var(--tblr-body-color);
    font-weight: 500;
}
.by b {
    font-weight: 600;
    color: var(--tblr-body-color);
}
.by .credit {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
@media (max-width: 767.98px) {
    .qrow {
        --tblr-gutter-x: 0;
        --tblr-gutter-y: 8px;
        margin-inline: 0;
        column-gap: 12px;
    }
    .qrow > * {
        padding-inline: 0;
    }
    .qrow img {
        width: 48px;
        height: 48px;
    }
    .qrow > .col {
        flex: 1 1 0;
        min-width: 0;
        overflow-wrap: anywhere;
    }
    .qbreak {
        flex-basis: 100%;
        height: 0;
    }
    .qrow > .votes :deep(.vote-control) {
        background: var(--tblr-bg-surface-secondary);
        border-radius: 99px;
        padding: 2px 4px;
    }
    .qrow > .votes {
        order: 1;
        margin-inline-start: 60px;
        min-width: 0;
    }
    .qrow > .qtime {
        order: 2;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 2px;
        font-size: 0.8125rem;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
    .qrow.is-sent > .qtime {
        margin-inline-start: 60px;
    }
    .qrow > .qsent {
        order: 3;
    }
}
</style>
