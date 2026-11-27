<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import {partyCrumbs} from '@/lib/breadcrumbs';
import {Head, Link, useForm} from '@inertiajs/vue3';
import EmptyState from '@/components/EmptyState.vue';
import ScheduleRequestControl from '@/components/ScheduleRequestControl.vue';
import PartyPlayer from '@/components/PartyPlayer.vue';
import Pagination from '@/components/Pagination.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {index, update} from '@/routes/parties/songs';
import type {LegacyPartyState, Paginator, When} from '@/types';

interface Reaction {
    nickname: string;
    userUrl: string;
    value: number;
    createdAt: When;
}

const props = defineProps<{
    party: {code: string; name: string; canManage: boolean};
    playerState: LegacyPartyState;
    providerName: string;
    trackUrl: string | null;
    song: {
        id: number;
        name: string;
        imageUrl: string;
        albumName: string;
        albumUrl: string;
        artists: {name: string; url: string}[];
        duration: string;
        votes: number;
        score: number;
        createdAt: When;
        queuedAt: When | null;
        scheduledFor: When | null;
        scheduledForInput: string;
        requestedBy: {nickname: string; url: string} | null;
        moderateUrl: string;
    };
    votes: Paginator<Reaction> & {total?: number};
    other: {id: number; url: string; votes: number; score: number; createdAt: When; queuedAt: When | null}[];
    ratings: Paginator<Reaction> & {total?: number};
}>();

const prefilled =
    typeof window === 'undefined' ? null : new URLSearchParams(window.location.search).get('scheduled_for');
const form = useForm({scheduled_for: prefilled ?? props.song.scheduledForInput});

function saveSchedule(): void {
    form.put(update({party: props.party.code, song: props.song.id}).url, {preserveScroll: true});
}

function clearSchedule(): void {
    form.scheduled_for = '';
    saveSchedule();
}
</script>

<template>
    <AppLayout :title="`${song.name} - ${party.name}`">
        <template #breadcrumbs>
            <Breadcrumbs
                :items="[...partyCrumbs(party), {label: 'Requests', href: index(party.code).url}, {label: song.name}]"
            />
        </template>
        <template #header>
            <PartyPlayer :code="party.code" :initial-state="playerState" />
        </template>
        <Head :title="`${song.name} - ${party.name}`" />
        <div class="page-header mt-0">
            <h1>{{ song.name }}</h1>
        </div>

        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-lg-2 d-lg-block d-none">
                                <img
                                    :src="song.imageUrl"
                                    alt=""
                                    class="rounded"
                                    loading="lazy"
                                    decoding="async"
                                    :title="song.albumName"
                                />
                            </div>
                            <div class="col-lg-10">
                                <div class="datagrid">
                                    <div>
                                        <div class="datagrid-title">Artists</div>
                                        <div>
                                            <template v-for="(artist, position) in song.artists" :key="artist.url">
                                                <Link :href="artist.url">{{ artist.name }}</Link
                                                ><template v-if="position < song.artists.length - 1">, </template>
                                            </template>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="datagrid-title">Album</div>
                                        <div>
                                            <Link :href="song.albumUrl">{{ song.albumName }}</Link>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="datagrid-title">Duration</div>
                                        <div>{{ song.duration }}</div>
                                    </div>
                                    <div>
                                        <div class="datagrid-title">Votes</div>
                                        <div>{{ song.votes }}</div>
                                    </div>
                                    <div>
                                        <div class="datagrid-title">Score</div>
                                        <div>{{ song.score }}</div>
                                    </div>
                                    <div>
                                        <div class="datagrid-title">Requested</div>
                                        <div>
                                            <span :title="song.createdAt.iso">{{ song.createdAt.human }}</span>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="datagrid-title">Requested By</div>
                                        <div>
                                            <Link v-if="song.requestedBy" :href="song.requestedBy.url">{{
                                                song.requestedBy.nickname
                                            }}</Link>
                                            <template v-else>Fallback Track</template>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="datagrid-title">Sent to {{ providerName }}</div>
                                        <div>
                                            <span v-if="song.queuedAt" :title="song.queuedAt.iso">{{
                                                song.queuedAt.human
                                            }}</span>
                                            <span v-else class="text-muted">Not Sent</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer align-content-end d-flex btn-list">
                        <a :href="song.moderateUrl" class="ms-auto btn btn-outline-danger">
                            <i class="icon ti ti-ban"></i>
                            Moderate
                        </a>
                        <a v-if="trackUrl" :href="trackUrl" class="btn btn-green text-white" target="_blank">
                            <i class="icon ti ti-external-link"></i>
                            View on {{ providerName }}
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="party.canManage" class="row mb-4">
            <div class="col-12">
                <form class="card" data-test="schedule-card" @submit.prevent="saveSchedule">
                    <div class="card-header">
                        <h2 class="card-title">Schedule</h2>
                    </div>
                    <div class="card-body">
                        <p class="mb-3" data-test="schedule-current">
                            <span class="datagrid-title d-inline me-2">Currently</span>
                            <span v-if="song.scheduledFor" :title="song.scheduledFor.iso">{{
                                song.scheduledFor.human
                            }}</span>
                            <span v-else class="text-muted">Not scheduled</span>
                        </p>
                        <ScheduleRequestControl v-model="form.scheduled_for" label="Scheduled for (party time)" />
                        <p v-if="form.errors.scheduled_for" class="invalid-feedback d-block">
                            {{ form.errors.scheduled_for }}
                        </p>
                    </div>
                    <div class="card-footer d-flex btn-list">
                        <button
                            type="button"
                            class="btn btn-outline-secondary"
                            data-test="schedule-clear"
                            :disabled="form.processing || !song.scheduledFor"
                            @click="clearSchedule"
                        >
                            Clear
                        </button>
                        <button type="submit" class="btn btn-primary ms-auto" :disabled="form.processing">Save</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <h2>Votes</h2>
                <div v-if="votes.data.length > 0" class="card" data-test="votes">
                    <div class="table-responsive">
                        <table class="table table-hover table-vcenter text-nowrap card-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th class="text-center">Vote</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(vote, position) in votes.data" :key="position">
                                    <td>
                                        <Link :href="vote.userUrl">{{ vote.nickname }}</Link>
                                    </td>
                                    <td class="text-center">
                                        <i
                                            v-if="vote.value > 0"
                                            class="icon ti ti-arrow-big-up-filled text-success"
                                        ></i>
                                        <i v-else class="icon ti ti-arrow-big-down-filled text-danger"></i>
                                    </td>
                                    <td>
                                        <span :title="vote.createdAt.iso">{{ vote.createdAt.human }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer d-flex align-items-center">
                        <Pagination class="ms-auto" :paginator="votes" />
                    </div>
                </div>
                <EmptyState v-else icon="ti-thumb-up-off" title="No votes yet" data-test="no-votes" />
            </div>
            <div class="col-md-6">
                <h2>Other Appearances</h2>
                <div v-if="other.length > 0" class="card" data-test="other">
                    <div class="table-responsive">
                        <table class="table table-hover table-vcenter text-nowrap card-table">
                            <thead>
                                <tr>
                                    <th>Requested</th>
                                    <th class="text-center">Votes</th>
                                    <th class="text-center">Score</th>
                                    <th>Sent to {{ providerName }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="appearance in other" :key="appearance.id">
                                    <td>
                                        <Link :href="appearance.url" :title="appearance.createdAt.iso">{{
                                            appearance.createdAt.human
                                        }}</Link>
                                    </td>
                                    <td class="text-center">{{ appearance.votes }}</td>
                                    <td class="text-center">{{ appearance.score }}</td>
                                    <td>
                                        <span v-if="appearance.queuedAt" :title="appearance.queuedAt.iso">{{
                                            appearance.queuedAt.human
                                        }}</span>
                                        <span v-else class="text-muted">Not Sent to {{ providerName }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <EmptyState v-else icon="ti-playlist-off" title="No other appearances" data-test="no-other" />
            </div>
        </div>
        <div class="row mt-4">
            <div class="col-md-6">
                <h2>Ratings</h2>
                <div v-if="ratings.data.length > 0" class="card" data-test="ratings">
                    <div class="table-responsive">
                        <table class="table table-hover table-vcenter text-nowrap card-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th class="text-center">Rating</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(rating, position) in ratings.data" :key="position">
                                    <td>
                                        <Link :href="rating.userUrl">{{ rating.nickname }}</Link>
                                    </td>
                                    <td class="text-center">
                                        <i v-if="rating.value > 0" class="icon ti ti-thumb-up-filled text-success"></i>
                                        <i v-else class="icon ti ti-thumb-down-filled text-danger"></i>
                                    </td>
                                    <td>
                                        <span :title="rating.createdAt.iso">{{ rating.createdAt.human }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer d-flex align-items-center">
                        <Pagination class="ms-auto" :paginator="ratings" />
                    </div>
                </div>
                <EmptyState v-else icon="ti-star-off" title="No ratings yet" data-test="no-ratings" />
            </div>
        </div>
    </AppLayout>
</template>
