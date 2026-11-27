<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import {partyCrumbs} from '@/lib/breadcrumbs';
import {Head, Link, router} from '@inertiajs/vue3';
import {ref} from 'vue';
import PartyPlayer from '@/components/PartyPlayer.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type {LegacyPartyState, When} from '@/types';

interface TrackRow {
    id: number;
    songName: string;
    albumName: string;
    imageUrl: string;
    artists: string[];
    url: string;
}

interface RequestRow extends TrackRow {
    status: 'played' | 'waiting' | 'flagged';
    score: number;
    createdAt: When | null;
}

interface VoteRow extends TrackRow {
    value: number;
    createdAt: When | null;
}

interface FlagRow extends TrackRow {
    reason: string;
    reviewUrl: string | null;
    outcome: string;
}

const props = defineProps<{
    party: {code: string; name: string; canManage: boolean};
    playerState: LegacyPartyState;
    member: {
        id: number;
        nickname: string;
        role: string;
        roleCode: string;
        createdAt: When | null;
        lastRequestAt?: When | null;
        requestCount?: number;
        voteCount?: number;
        flaggedCount?: number;
        canBan?: boolean;
    };
    requests?: RequestRow[];
    votes?: VoteRow[];
    flags?: FlagRow[];
}>();

const roleBadges: Record<string, string> = {
    owner: 'bg-azure-lt',
    moderator: 'bg-purple-lt',
    vip: 'bg-yellow-lt',
    user: 'bg-secondary-lt',
    banned: 'bg-red-lt',
};

const statuses: Record<RequestRow['status'], {label: string; badge: string}> = {
    played: {label: 'Played', badge: 'bg-green-lt'},
    waiting: {label: 'Waiting', badge: 'bg-blue-lt'},
    flagged: {label: 'Flagged', badge: 'bg-orange-lt'},
};

const usersUrl = `/parties/${props.party.code}/users`;
const memberUrl = `${usersUrl}/${props.member.id}`;
const banOpen = ref(false);

function confirmBan(): void {
    router.patch(memberUrl, {role: 'banned'});
}

function subtitle(): string {
    const parts = [];
    if (props.member.createdAt) {
        parts.push(`Joined ${props.member.createdAt.human}.`);
    }
    if (props.member.lastRequestAt) {
        parts.push(`Last request ${props.member.lastRequestAt.human}.`);
    }
    return parts.join(' ');
}
</script>

<template>
    <AppLayout :title="`${member.nickname} - ${party.name}`">
        <template #breadcrumbs>
            <Breadcrumbs
                :items="[...partyCrumbs(party), {label: 'Members', href: usersUrl}, {label: member.nickname}]"
            />
        </template>
        <template #header>
            <PartyPlayer :code="party.code" :initial-state="playerState" />
        </template>
        <Head :title="`${member.nickname} - ${party.name}`" />
        <div class="mp-page-head">
            <div>
                <h1>
                    {{ member.nickname }}
                    <span
                        class="badge align-middle ms-1"
                        :class="roleBadges[member.roleCode] ?? 'bg-secondary-lt'"
                        data-test="role-badge"
                        >{{ member.role }}</span
                    >
                </h1>
                <p>{{ subtitle() }}</p>
            </div>
            <div v-if="party.canManage" class="btn-list mp-member-actions" data-test="member-actions">
                <Link :href="`${memberUrl}/edit`" class="btn btn-primary" data-test="change-role">
                    <i class="icon ti ti-user-cog" aria-hidden="true"></i>
                    Change role
                </Link>
                <button
                    v-if="member.canBan"
                    type="button"
                    class="btn btn-outline-danger"
                    data-test="ban-member"
                    @click="banOpen = true"
                >
                    <i class="icon ti ti-ban" aria-hidden="true"></i>
                    Ban {{ member.nickname }}
                </button>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3 g-md-4 mp-stat">
                    <div class="col-6 col-md d-none d-md-block">
                        <span class="text-secondary">Role</span><b>{{ member.role }}</b>
                    </div>
                    <div class="col-6 col-md">
                        <span class="text-secondary">Requests</span><b>{{ member.requestCount ?? 0 }}</b>
                    </div>
                    <div class="col-6 col-md">
                        <span class="text-secondary">Votes cast</span><b>{{ member.voteCount ?? 0 }}</b>
                    </div>
                    <div class="col-6 col-md">
                        <span class="text-secondary">Flagged</span>
                        <b :class="(member.flaggedCount ?? 0) > 0 ? 'text-orange' : 'text-secondary'">{{
                            member.flaggedCount ?? 0
                        }}</b>
                    </div>
                    <div class="col-6 col-md">
                        <span class="text-secondary">Joined</span><b class="fs-4">{{ member.createdAt?.human }}</b>
                    </div>
                </div>
            </div>
        </div>

        <h2>Requests</h2>
        <div class="card mb-4">
            <p v-if="!requests?.length" class="card-body text-secondary mb-0" data-test="requests-empty">
                No requests yet.
            </p>
            <template v-else>
                <div class="table-responsive d-none d-md-block">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>Track</th>
                                <th>Status</th>
                                <th class="text-end">Score</th>
                                <th>Requested</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in requests" :key="row.id" data-test="request-row">
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img
                                            :src="row.imageUrl"
                                            :title="row.albumName"
                                            alt=""
                                            width="40"
                                            height="40"
                                            class="rounded"
                                            loading="lazy"
                                        />
                                        <div>
                                            <Link :href="row.url">{{ row.songName }}</Link>
                                            <div class="text-secondary small">{{ row.artists.join(', ') }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span
                                        class="badge"
                                        :class="statuses[row.status].badge"
                                        data-test="request-status"
                                        >{{ statuses[row.status].label }}</span
                                    >
                                </td>
                                <td class="mp-num text-end">{{ row.score > 0 ? `+${row.score}` : row.score }}</td>
                                <td>
                                    <span v-if="row.createdAt" :title="row.createdAt.iso">{{
                                        row.createdAt.human
                                    }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="list-group list-group-flush d-md-none">
                    <div
                        v-for="row in requests"
                        :key="row.id"
                        class="list-group-item d-flex align-items-center gap-2"
                        data-test="request-card"
                    >
                        <img :src="row.imageUrl" alt="" width="40" height="40" class="rounded" loading="lazy" />
                        <div class="flex-fill min-w-0">
                            <Link :href="row.url">{{ row.songName }}</Link>
                            <div class="text-secondary small">
                                {{ row.artists.join(', ')
                                }}<template v-if="row.createdAt"> &middot; {{ row.createdAt.human }}</template>
                            </div>
                        </div>
                        <span class="badge" :class="statuses[row.status].badge">{{ statuses[row.status].label }}</span>
                    </div>
                </div>
            </template>
        </div>

        <h2>Votes</h2>
        <div class="card mb-4">
            <p v-if="!votes?.length" class="card-body text-secondary mb-0" data-test="votes-empty">No votes yet.</p>
            <template v-else>
                <div class="table-responsive d-none d-md-block">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>Track</th>
                                <th>Vote</th>
                                <th>When</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="vote in votes" :key="vote.id" data-test="vote-row">
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img
                                            :src="vote.imageUrl"
                                            :title="vote.albumName"
                                            alt=""
                                            width="40"
                                            height="40"
                                            class="rounded"
                                            loading="lazy"
                                        />
                                        <div>
                                            <Link :href="vote.url">{{ vote.songName }}</Link>
                                            <div class="text-secondary small">{{ vote.artists.join(', ') }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <i
                                        v-if="vote.value > 0"
                                        class="icon ti ti-arrow-big-up-filled text-success"
                                        aria-hidden="true"
                                        data-test="vote-up"
                                    ></i>
                                    <i
                                        v-else
                                        class="icon ti ti-arrow-big-down-filled text-danger"
                                        aria-hidden="true"
                                        data-test="vote-down"
                                    ></i>
                                    <span class="visually-hidden">{{ vote.value > 0 ? 'Upvote' : 'Downvote' }}</span>
                                </td>
                                <td>
                                    <span v-if="vote.createdAt" :title="vote.createdAt.iso">{{
                                        vote.createdAt.human
                                    }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="list-group list-group-flush d-md-none">
                    <div
                        v-for="vote in votes"
                        :key="vote.id"
                        class="list-group-item d-flex align-items-center gap-2"
                        data-test="vote-card"
                    >
                        <img :src="vote.imageUrl" alt="" width="40" height="40" class="rounded" loading="lazy" />
                        <div class="flex-fill min-w-0">
                            <Link :href="vote.url">{{ vote.songName }}</Link>
                            <div class="text-secondary small">
                                {{ vote.artists.join(', ')
                                }}<template v-if="vote.createdAt"> &middot; {{ vote.createdAt.human }}</template>
                            </div>
                        </div>
                        <i
                            v-if="vote.value > 0"
                            class="icon ti ti-arrow-big-up-filled text-success"
                            aria-hidden="true"
                        ></i>
                        <i v-else class="icon ti ti-arrow-big-down-filled text-danger" aria-hidden="true"></i>
                        <span class="visually-hidden">{{ vote.value > 0 ? 'Upvote' : 'Downvote' }}</span>
                    </div>
                </div>
            </template>
        </div>

        <h2>Flags</h2>
        <div class="card">
            <p v-if="!flags?.length" class="card-body text-secondary mb-0" data-test="flags-empty">
                Nothing from this member has been flagged.
            </p>
            <template v-else>
                <div class="table-responsive d-none d-md-block">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>Track</th>
                                <th>Why it was flagged</th>
                                <th>Outcome</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="flag in flags" :key="flag.id" data-test="flag-row">
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img
                                            :src="flag.imageUrl"
                                            :title="flag.albumName"
                                            alt=""
                                            width="40"
                                            height="40"
                                            class="rounded"
                                            loading="lazy"
                                        />
                                        <div>
                                            <Link :href="flag.url">{{ flag.songName }}</Link>
                                            <div class="text-secondary small">{{ flag.artists.join(', ') }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    {{ flag.reason }}
                                    <Link v-if="flag.reviewUrl" :href="flag.reviewUrl" data-test="review-link"
                                        >See review</Link
                                    >
                                </td>
                                <td>
                                    <span class="badge bg-orange-lt">{{ flag.outcome }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="list-group list-group-flush d-md-none">
                    <div v-for="flag in flags" :key="flag.id" class="list-group-item" data-test="flag-card">
                        <div class="d-flex align-items-center gap-2">
                            <img :src="flag.imageUrl" alt="" width="40" height="40" class="rounded" loading="lazy" />
                            <div class="flex-fill min-w-0">
                                <Link :href="flag.url">{{ flag.songName }}</Link>
                                <div class="text-secondary small">{{ flag.artists.join(', ') }}</div>
                            </div>
                            <span class="badge bg-orange-lt">{{ flag.outcome }}</span>
                        </div>
                        <div class="mt-2">
                            {{ flag.reason }}
                            <Link v-if="flag.reviewUrl" :href="flag.reviewUrl">See review</Link>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <ConfirmDialog
            v-model="banOpen"
            :title="`Ban ${member.nickname}?`"
            :message="`${member.nickname} will not be able to make Requests or vote. You can change their role back at any time.`"
            :confirm-label="`Ban ${member.nickname}`"
            @confirm="confirmBan"
        />
    </AppLayout>
</template>
