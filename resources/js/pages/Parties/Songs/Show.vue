<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import {partyCrumbs} from '@/lib/breadcrumbs';
import {Head, Link, router, useForm} from '@inertiajs/vue3';
import EmptyState from '@/components/EmptyState.vue';
import PageHead from '@/components/PageHead.vue';
import RowMenu from '@/components/RowMenu.vue';
import ScheduleRequestControl from '@/components/ScheduleRequestControl.vue';
import PartyPlayer from '@/components/PartyPlayer.vue';
import Pagination from '@/components/Pagination.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {index, update} from '@/routes/parties/songs';
import type {LegacyPartyState, Paginator, When} from '@/types';

interface Reaction {
    nickname: string;
    userUrl: string | null;
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
        requestedBy: {nickname: string; url: string | null} | null;
        moderateUrl: string;
        removeUrl: string;
    };
    votes: Paginator<Reaction> & {total?: number};
    aiReview: {outcome: string; outcomeLabel: string; reason: string | null; criterion: string | null; url: string} | null;
    history: {icon: string; text: string; at: When | null; url: string | null}[];
}>();

const form = useForm({scheduled_for: props.song.scheduledForInput});

const OUTCOME_BADGES: Record<string, {badge: string; icon: string}> = {
    allow: {badge: 'bg-green-lt', icon: 'ti-check'},
    flag: {badge: 'bg-yellow-lt', icon: 'ti-flag'},
    reject: {badge: 'bg-red-lt', icon: 'ti-x'},
    error: {badge: 'bg-secondary-lt', icon: 'ti-alert-triangle'},
};

function saveSchedule(): void {
    form.put(update({party: props.party.code, song: props.song.id}).url, {preserveScroll: true});
}

function clearSchedule(): void {
    form.scheduled_for = '';
    saveSchedule();
}

function removeRequest(): void {
    router.delete(props.song.removeUrl);
}

function initials(name: string): string {
    return name.slice(0, 2).toUpperCase();
}

function signed(score: number): string {
    return score > 0 ? `+${score}` : `${score}`;
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
        <PageHead
            :title="song.name"
            :summary="`${song.artists.map((artist) => artist.name).join(', ')} · ${
                song.requestedBy ? `Request from ${song.requestedBy.nickname}` : 'Fallback track'
            }, ${song.createdAt.human}`"
        >
            <a v-if="trackUrl" :href="trackUrl" class="btn btn-outline-secondary" target="_blank" rel="noopener">
                <i class="icon ti ti-external-link" aria-hidden="true"></i>
                View on {{ providerName }}
            </a>
            <RowMenu label="More actions">
                <a class="dropdown-item" :href="song.moderateUrl" data-test="moderate">
                    <i class="icon ti ti-ban me-2" aria-hidden="true"></i>Moderate
                </a>
                <div class="dropdown-divider"></div>
                <button type="button" class="dropdown-item text-danger" data-test="remove-request" @click="removeRequest">
                    <i class="icon ti ti-trash me-2" aria-hidden="true"></i>Remove Request
                    <small class="d-block text-secondary">Takes it out of the Queue</small>
                </button>
            </RowMenu>
        </PageHead>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="mp-stack">
                    <div class="card" data-test="track-card">
                        <div class="card-body">
                            <div class="row g-4">
                                <div class="col-auto">
                                    <img
                                        :src="song.imageUrl"
                                        alt=""
                                        class="rounded mp-detail-art"
                                        width="168"
                                        height="168"
                                        decoding="async"
                                        :title="song.albumName"
                                    />
                                </div>
                                <div class="col-12 col-md">
                                    <dl class="mp-facts">
                                        <dt>{{ song.artists.length === 1 ? 'Artist' : 'Artists' }}</dt>
                                        <dd>
                                            <template v-for="(artist, position) in song.artists" :key="artist.url">
                                                <Link :href="artist.url">{{ artist.name }}</Link
                                                ><template v-if="position < song.artists.length - 1">, </template>
                                            </template>
                                        </dd>
                                        <dt>Album</dt>
                                        <dd>
                                            <Link :href="song.albumUrl">{{ song.albumName }}</Link>
                                        </dd>
                                        <dt>Duration</dt>
                                        <dd class="mp-num">{{ song.duration }}</dd>
                                        <dt>Requested by</dt>
                                        <dd data-test="requested-by">
                                            <span v-if="song.requestedBy" class="mp-who">
                                                <span class="avatar rounded-circle">{{
                                                    initials(song.requestedBy.nickname)
                                                }}</span>
                                                <Link v-if="song.requestedBy.url" :href="song.requestedBy.url">{{
                                                    song.requestedBy.nickname
                                                }}</Link>
                                                <template v-else>{{ song.requestedBy.nickname }}</template>
                                            </span>
                                            <span v-else class="text-secondary">Fallback Track</span>
                                        </dd>
                                        <dt>Requested</dt>
                                        <dd>
                                            <span :title="song.createdAt.iso">{{ song.createdAt.human }}</span>
                                        </dd>
                                        <dt>Sent to Player</dt>
                                        <dd>
                                            <span v-if="song.queuedAt" :title="song.queuedAt.iso">{{
                                                song.queuedAt.human
                                            }}</span>
                                            <span v-else class="text-secondary">Not yet</span>
                                        </dd>
                                        <dt>Votes</dt>
                                        <dd class="mp-num" data-test="vote-summary">
                                            {{ song.votes }} {{ song.votes === 1 ? 'vote' : 'votes' }}, score
                                            {{ signed(song.score) }}
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card" data-test="ai-review">
                        <div class="card-header">
                            <h2 class="card-title">AI review</h2>
                        </div>
                        <div v-if="aiReview" class="card-body d-flex flex-wrap align-items-center gap-3">
                            <span
                                class="badge fs-4 py-2 px-3"
                                :class="(OUTCOME_BADGES[aiReview.outcome] ?? OUTCOME_BADGES.error).badge"
                                data-test="ai-outcome"
                                ><i
                                    class="icon ti me-1"
                                    :class="(OUTCOME_BADGES[aiReview.outcome] ?? OUTCOME_BADGES.error).icon"
                                    aria-hidden="true"
                                ></i
                                >{{ aiReview.outcomeLabel }}</span
                            >
                            <span class="text-secondary flex-fill" data-test="ai-reason">
                                {{ aiReview.reason ?? 'No reason given.' }}
                                {{ aiReview.criterion ? `Criterion: ${aiReview.criterion}.` : 'No criterion applied.' }}
                            </span>
                            <Link :href="aiReview.url" class="mp-touch">View AI review</Link>
                        </div>
                        <div v-else class="card-body text-secondary" data-test="no-ai-review">
                            This Request was not reviewed by AI.
                        </div>
                    </div>

                    <div class="card" data-test="history">
                        <div class="card-header">
                            <h2 class="card-title">History</h2>
                        </div>
                        <ul class="mp-timeline">
                            <li v-for="(event, position) in history" :key="position">
                                <i class="icon ti" :class="event.icon" aria-hidden="true"></i>
                                <span>
                                    <Link v-if="event.url" :href="event.url">{{ event.text }}</Link>
                                    <template v-else>{{ event.text }}</template>
                                </span>
                                <span v-if="event.at" class="text-secondary" :title="event.at.iso">{{
                                    event.at.human
                                }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="mp-stack">
                    <form
                        v-if="party.canManage"
                        id="scheduled-for"
                        class="card"
                        data-test="schedule-card"
                        @submit.prevent="saveSchedule"
                    >
                        <div class="card-header">
                            <h2 class="card-title">Schedule</h2>
                        </div>
                        <div class="card-body">
                            <p class="text-secondary mb-3" data-test="schedule-current">
                                <template v-if="song.scheduledFor"
                                    >Scheduled for
                                    <span :title="song.scheduledFor.iso">{{ song.scheduledFor.human }}</span
                                    >.</template
                                >
                                <template v-else
                                    >Not scheduled. Pick a time and Music Party sends this Request to the Player
                                    then.</template
                                >
                            </p>
                            <ScheduleRequestControl v-model="form.scheduled_for" label="Send at (party time)" />
                            <p v-if="form.errors.scheduled_for" class="invalid-feedback d-block">
                                {{ form.errors.scheduled_for }}
                            </p>
                        </div>
                        <div class="card-footer d-flex gap-2">
                            <button
                                type="button"
                                class="btn btn-outline-secondary"
                                data-test="schedule-clear"
                                :disabled="form.processing || !song.scheduledFor"
                                @click="clearSchedule"
                            >
                                Clear schedule
                            </button>
                            <button type="submit" class="btn btn-primary ms-auto" :disabled="form.processing">
                                Save schedule
                            </button>
                        </div>
                    </form>

                    <div class="card" data-test="votes-card">
                        <div class="card-header">
                            <h2 class="card-title">Votes</h2>
                        </div>
                        <ul v-if="votes.data.length > 0" class="mp-timeline" data-test="votes">
                            <li v-for="(vote, position) in votes.data" :key="position">
                                <i
                                    v-if="vote.value > 0"
                                    class="icon ti ti-arrow-big-up-filled text-success"
                                    aria-label="Up vote"
                                ></i>
                                <i v-else class="icon ti ti-arrow-big-down text-danger" aria-label="Down vote"></i>
                                <span class="mp-who">
                                    <span class="avatar rounded-circle">{{ initials(vote.nickname) }}</span>
                                    <Link v-if="vote.userUrl" :href="vote.userUrl">{{ vote.nickname }}</Link>
                                    <template v-else>{{ vote.nickname }}</template>
                                </span>
                                <span class="text-secondary" :title="vote.createdAt.iso">{{
                                    vote.createdAt.human
                                }}</span>
                            </li>
                        </ul>
                        <EmptyState v-else icon="ti-thumb-up-off" title="No votes yet" data-test="no-votes" />
                        <div v-if="votes.last_page > 1" class="card-footer d-flex align-items-center">
                            <Pagination class="ms-auto" :paginator="votes" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
