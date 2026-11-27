<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import EmptyState from '@/components/EmptyState.vue';
import StatePill from '@/components/StatePill.vue';
import {adminCrumbs} from '@/lib/breadcrumbs';
import {formatAge} from '@/lib/playbackStatus';
import {Head, Link, usePoll} from '@inertiajs/vue3';
import {computed, onBeforeUnmount, onMounted, ref} from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type {AdminDashboardParty, AdminDashboardProps} from '@/types';

const props = defineProps<AdminDashboardProps>();

const now = ref(Date.now());
let tick: ReturnType<typeof setInterval> | undefined;
onMounted(() => (tick = setInterval(() => (now.value = Date.now()), 5000)));
onBeforeUnmount(() => clearInterval(tick));
usePoll(30000, {only: ['generatedAt', 'liveParties', 'quietFor', 'attention', 'health', 'activity']});

const ago = (iso: string): string => formatAge((now.value - Date.parse(iso)) / 1000);
const checkedAgo = computed(() => ago(props.generatedAt));

const healthLinkLabels: Record<string, string> = {workers: 'Horizon', reverb: 'Pulse', spotify: 'Fix credentials'};
const dots = {ok: 'status-green', warn: 'status-yellow', error: 'status-red'};
const outsideApp = (url: string): boolean => url.includes('/horizon') || url.includes('/pulse');
const count = (value: number | null): string => (value === null ? '–' : String(value));
const counts = (party: AdminDashboardParty): string[] => [
    `${count(party.guestsOnline)} online`,
    `${party.requestsWaiting} waiting`,
    `${party.flagged} flagged`,
];
</script>

<template>
    <AppLayout title="Dashboard">
        <template #breadcrumbs>
            <Breadcrumbs :items="[...adminCrumbs(), {label: 'Dashboard'}]" />
        </template>
        <Head title="Dashboard" />
        <div class="mp-admin-dash">
            <div class="mp-page-head">
                <div>
                    <h1>Dashboard</h1>
                    <p>Live parties and anything on this instance that needs fixing.</p>
                </div>
                <div class="btn-list">
                    <a :href="links.pulse" class="btn" data-test="open-pulse"
                        ><i class="icon ti ti-activity"></i>Open Pulse</a
                    >
                    <a :href="links.horizon" class="btn" data-test="open-horizon"
                        ><i class="icon ti ti-list-details"></i>Open Horizon</a
                    >
                </div>
            </div>

            <div class="mp-stack">
                <div class="card" data-test="live-parties">
                    <div class="card-header">
                        <h2 class="card-title">
                            Live parties
                            <span v-if="liveParties.length > 0" class="mp-count ms-2">{{ liveParties.length }}</span>
                        </h2>
                        <span class="ms-auto text-secondary small">Updated {{ checkedAgo }}</span>
                    </div>
                    <EmptyState
                        v-if="liveParties.length === 0"
                        title="No parties are live"
                        icon="ti-playlist-off"
                        :description="`A party is live while its Player is reporting or guests are connected.${quietFor ? ` Nobody has been on this instance for ${quietFor}.` : ''}`"
                    >
                        <a :href="links.createParty" class="btn btn-primary" data-test="create-party"
                            ><i class="icon ti ti-plus"></i>Create a party</a
                        >
                    </EmptyState>
                    <template v-else>
                        <table class="table table-vcenter card-table d-none d-md-table">
                            <thead>
                                <tr>
                                    <th>Party</th>
                                    <th>Playback</th>
                                    <th>Now playing</th>
                                    <th class="text-end">Guests online</th>
                                    <th class="text-end">Requests waiting</th>
                                    <th class="text-end">Flagged</th>
                                    <th>Owner</th>
                                    <th><span class="visually-hidden">Open</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="party in liveParties" :key="party.code" data-test="party-row">
                                    <td>
                                        <Link :href="party.url" class="fw-medium text-nowrap">{{ party.name }}</Link>
                                        <span class="mp-sub">{{ party.code }}</span>
                                    </td>
                                    <td>
                                        <StatePill :state="party.state"
                                            ><template v-if="party.stateLabel" #default>{{
                                                party.stateLabel
                                            }}</template></StatePill
                                        >
                                        <span v-if="party.stateDetail" class="mp-sub">{{ party.stateDetail }}</span>
                                    </td>
                                    <td>
                                        <div v-if="party.track" class="mp-cell">
                                            <img
                                                v-if="party.track.image"
                                                class="mp-art"
                                                :src="party.track.image"
                                                alt=""
                                            />
                                            <div>
                                                <span class="mp-ellip">{{ party.track.name }}</span>
                                                <span class="mp-sub text-nowrap"
                                                    >{{ party.track.artists
                                                    }}{{ party.track.lastKnown ? ' · last known' : '' }}</span
                                                >
                                            </div>
                                        </div>
                                        <span v-else class="text-secondary">Nothing to play</span>
                                    </td>
                                    <td class="text-end mp-num">{{ count(party.guestsOnline) }}</td>
                                    <td class="text-end mp-num">{{ party.requestsWaiting }}</td>
                                    <td class="text-end mp-num">
                                        <span v-if="party.flagged > 0" class="badge bg-red text-red-fg">{{
                                            party.flagged
                                        }}</span>
                                        <span v-else class="text-secondary">0</span>
                                    </td>
                                    <td>{{ party.owner }}</td>
                                    <td class="text-end">
                                        <Link :href="party.url" class="btn btn-sm">Open Dashboard</Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <ul class="list-group list-group-flush d-md-none">
                            <li
                                v-for="party in liveParties"
                                :key="party.code"
                                class="list-group-item"
                                data-test="party-card"
                            >
                                <div class="d-flex align-items-start justify-content-between gap-2">
                                    <div class="min-w-0">
                                        <Link :href="party.url" class="fw-medium">{{ party.name }}</Link>
                                        <span class="mp-sub">{{ party.code }} · {{ party.owner }}</span>
                                    </div>
                                    <StatePill :state="party.state"
                                        ><template v-if="party.stateLabel" #default>{{
                                            party.stateLabel
                                        }}</template></StatePill
                                    >
                                </div>
                                <div v-if="party.track" class="mp-cell mt-2">
                                    <img v-if="party.track.image" class="mp-art" :src="party.track.image" alt="" />
                                    <div>
                                        <span class="mp-ellip">{{ party.track.name }}</span>
                                        <span class="mp-sub">{{ party.track.artists }}</span>
                                    </div>
                                </div>
                                <div v-if="party.stateDetail" class="mp-sub mt-2">{{ party.stateDetail }}</div>
                                <div class="d-flex flex-wrap gap-3 mt-2 small mp-num">
                                    <span v-for="text in counts(party)" :key="text">{{ text }}</span>
                                </div>
                                <Link :href="party.url" class="btn btn-sm mt-3">Open Dashboard</Link>
                            </li>
                        </ul>
                    </template>
                </div>

                <div class="mp-grid-7-5">
                    <div class="card" data-test="needs-attention">
                        <div class="card-header">
                            <h2 class="card-title">
                                Needs attention
                                <span v-if="attention.length > 0" class="mp-count is-attention ms-2">{{
                                    attention.length
                                }}</span>
                            </h2>
                        </div>
                        <EmptyState
                            v-if="attention.length === 0"
                            title="Nothing needs attention"
                            icon="ti-check"
                            all-clear
                            :description="`No expired Spotify links, stale Players, failed jobs or misconfigured sign-in providers. Checked ${checkedAgo}.`"
                        />
                        <ul v-else class="mp-checks">
                            <li v-for="item in attention" :key="item.key" data-test="attention-item">
                                <span
                                    class="status-dot"
                                    :class="item.severity === 'error' ? 'status-red' : 'status-yellow'"
                                ></span>
                                <div>
                                    <span class="mp-check-label">{{ item.title }}</span>
                                    <span class="mp-check-detail">{{ item.detail }}</span>
                                </div>
                                <a v-if="outsideApp(item.action.url)" :href="item.action.url" class="btn btn-sm">{{
                                    item.action.label
                                }}</a>
                                <Link v-else :href="item.action.url" class="btn btn-sm">{{ item.action.label }}</Link>
                            </li>
                        </ul>
                    </div>

                    <div class="card" data-test="system-health">
                        <div class="card-header">
                            <h2 class="card-title">System health</h2>
                            <span class="ms-auto text-secondary small">Checked {{ checkedAgo }}</span>
                        </div>
                        <ul class="mp-checks">
                            <li v-for="check in health" :key="check.key" data-test="health-item">
                                <span class="status-dot" :class="dots[check.status]"></span>
                                <div>
                                    <span class="mp-check-label">{{ check.label }}</span>
                                    <span class="mp-check-detail">{{ check.detail }}</span>
                                </div>
                                <template v-if="check.link">
                                    <a
                                        v-if="outsideApp(check.link)"
                                        :href="check.link"
                                        class="btn btn-sm btn-ghost-secondary"
                                        >{{ healthLinkLabels[check.key] }}</a
                                    >
                                    <Link v-else :href="check.link" class="btn btn-sm btn-ghost-secondary">{{
                                        healthLinkLabels[check.key]
                                    }}</Link>
                                </template>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="card" data-test="activity">
                    <div class="card-header">
                        <h2 class="card-title">Activity</h2>
                    </div>
                    <div class="card-body">
                        <div class="datagrid">
                            <div>
                                <div class="datagrid-title">Sign-ups this week</div>
                                <div class="datagrid-content mp-num">{{ activity.stats.signUpsThisWeek }}</div>
                            </div>
                            <div>
                                <div class="datagrid-title">Parties created this week</div>
                                <div class="datagrid-content mp-num">{{ activity.stats.partiesThisWeek }}</div>
                            </div>
                            <div>
                                <div class="datagrid-title">Played Tracks today</div>
                                <div class="datagrid-content mp-num">{{ activity.stats.playedToday }}</div>
                            </div>
                            <div>
                                <div class="datagrid-title">Users</div>
                                <div class="datagrid-content mp-num">{{ activity.stats.users }}</div>
                            </div>
                        </div>
                    </div>
                    <ul v-if="activity.recent.length > 0" class="list-group list-group-flush">
                        <li
                            v-for="event in activity.recent"
                            :key="event.at + event.text"
                            class="list-group-item d-flex align-items-start gap-3"
                            data-test="activity-item"
                        >
                            <i class="icon ti text-secondary mt-1" :class="event.icon"></i>
                            <span class="flex-fill">{{ event.text }}</span>
                            <span class="text-secondary small text-nowrap">{{ ago(event.at) }}</span>
                        </li>
                    </ul>
                    <div v-else class="card-body border-top text-secondary">Nothing yet this week.</div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
