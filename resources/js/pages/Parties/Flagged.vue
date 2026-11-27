<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import {partyCrumbs} from '@/lib/breadcrumbs';
import {Head, router} from '@inertiajs/vue3';
import axios from 'axios';
import {computed, ref} from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import EmptyState from '@/components/EmptyState.vue';
import ModerationTabs from '@/components/ModerationTabs.vue';
import PartyPlayer from '@/components/PartyPlayer.vue';
import {useEcho} from '@/composables/useEcho';
import PageHead from '@/components/PageHead.vue';
import SearchBar from '@/components/SearchBar.vue';
import type {SearchChip} from '@/components/SearchBar.vue';
import {flaggedApproveUrl, flaggedBulkApproveUrl, flaggedBulkRejectUrl, flaggedRejectUrl} from '@/lib/flagged';
import AppLayout from '@/layouts/AppLayout.vue';
import type {FlaggedRequest, LegacyPartyState, ModerationTabsProps} from '@/types';

const props = defineProps<{
    party: {code: string; name: string; canManage?: boolean; flagRequests?: boolean};
    flagged: FlaggedRequest[];
    moderationTabs: ModerationTabsProps;
    playerState: LegacyPartyState;
}>();

const items = ref<FlaggedRequest[]>([...props.flagged]);
const rejecting = ref<FlaggedRequest[]>([]);
const selected = ref<number[]>([]);
const search = ref('');
const source = ref<'rule' | 'ai' | ''>('');
const rejectOpen = ref(false);
const rejectMessage = ref('');
const error = ref<string | null>(null);

const channel = useEcho().privateChannel(`party.${props.party.code}.managers`) as {
    listen<T>(event: string, cb: (payload: T) => void): unknown;
};

channel.listen<FlaggedRequest>('FlaggedRequestAdded', (payload) => {
    if (!items.value.some((item) => item.id === payload.id)) {
        items.value = [payload, ...items.value];
    }
});

channel.listen<{id: number}>('FlaggedRequestResolved', (payload) => {
    remove(payload.id);
});

function remove(id: number): void {
    items.value = items.value.filter((item) => item.id !== id);
    selected.value = selected.value.filter((selectedId) => selectedId !== id);
}

const isAi = (item: FlaggedRequest): boolean => item.ai_review_url !== null || item.ai_review != null;

const visible = computed(() => {
    const needle = search.value.trim().toLowerCase();

    return items.value.filter((item) => {
        if (source.value === 'ai' && !isAi(item)) {
            return false;
        }
        if (source.value === 'rule' && isAi(item)) {
            return false;
        }

        return (
            needle === '' ||
            [item.track.name, item.requester.name, item.reason ?? '', ...item.track.artists.map((a) => a.name)]
                .join(' ')
                .toLowerCase()
                .includes(needle)
        );
    });
});

const chips = computed<SearchChip[]>(() => [
    {
        key: 'rule',
        label: 'Matched a rule',
        count: items.value.filter((item) => !isAi(item)).length,
        pressed: source.value === 'rule',
    },
    {
        key: 'ai',
        label: 'AI review',
        count: items.value.filter(isAi).length,
        pressed: source.value === 'ai',
    },
]);

const visibleIds = computed(() => visible.value.map((item) => item.id));
const allSelected = computed(
    () => visibleIds.value.length > 0 && visibleIds.value.every((id) => selected.value.includes(id)),
);

function toggleChip(key: string): void {
    const next = key as 'rule' | 'ai';
    source.value = source.value === next ? '' : next;
}

function toggleAll(): void {
    selected.value = allSelected.value ? [] : [...visibleIds.value];
}

function toggleOne(id: number): void {
    selected.value = selected.value.includes(id)
        ? selected.value.filter((selectedId) => selectedId !== id)
        : [...selected.value, id];
}

function minutesAgo(iso: string): string {
    const minutes = Math.max(0, Math.round((Date.now() - new Date(iso).getTime()) / 60000));
    if (minutes < 1) {
        return 'just now';
    }
    if (minutes < 60) {
        return `${minutes} minute${minutes === 1 ? '' : 's'} ago`;
    }
    const hours = Math.round(minutes / 60);
    if (hours < 24) {
        return `${hours} hour${hours === 1 ? '' : 's'} ago`;
    }
    const days = Math.round(hours / 24);

    return `${days} day${days === 1 ? '' : 's'} ago`;
}

async function send(ids: number[], url: string, data?: Record<string, unknown>): Promise<void> {
    error.value = null;
    try {
        await axios.post(url, data);
        ids.forEach(remove);
    } catch {
        error.value = 'That request could not be updated.';
        router.reload({only: ['flagged']});
    }
}

function approve(item: FlaggedRequest): Promise<void> {
    return send([item.id], flaggedApproveUrl(props.party.code, item.id));
}

function approveSelected(): Promise<void> {
    return send([...selected.value], flaggedBulkApproveUrl(props.party.code), {ids: [...selected.value]});
}

function askReject(targets: FlaggedRequest[]): void {
    rejecting.value = targets;
    rejectMessage.value = '';
    rejectOpen.value = true;
}

function askRejectSelected(): void {
    askReject(items.value.filter((item) => selected.value.includes(item.id)));
}

function confirmReject(): void {
    const targets = rejecting.value;
    if (targets.length === 0) {
        return;
    }
    const message = rejectMessage.value.trim();
    const data = message === '' ? undefined : {message};
    const ids = targets.map((item) => item.id);
    if (ids.length === 1) {
        void send(ids, flaggedRejectUrl(props.party.code, ids[0]), data);

        return;
    }
    void send(ids, flaggedBulkRejectUrl(props.party.code), {ids, ...data});
}

const confidenceText = (confidence: number | null | undefined): string =>
    confidence == null ? '' : `, ${Math.round(confidence * 100)}% confident`;
</script>

<template>
    <AppLayout :title="`Moderation - ${party.name}`">
        <template #breadcrumbs>
            <Breadcrumbs :items="[...partyCrumbs(party), {label: 'Moderation'}]" />
        </template>
        <template #header>
            <PartyPlayer :code="party.code" :initial-state="playerState" />
        </template>
        <Head :title="`Moderation - ${party.name}`" />
        <div class="mp-touch">
            <PageHead
                title="Moderation"
                summary="Review what the rules and AI reviews held back, and keep the rules that decide it."
            />
            <ModerationTabs :code="party.code" :tabs="moderationTabs" active="flagged" />
            <div v-if="error" class="alert alert-danger" role="alert" data-test="error">{{ error }}</div>
            <div
                v-if="party.flagRequests === false"
                class="alert alert-warning d-flex flex-wrap align-items-center gap-3"
                role="status"
                data-test="not-held"
            >
                <i class="icon ti ti-flag" aria-hidden="true"></i>
                <span class="flex-fill"
                    >Flagged Requests aren't held. They go straight to the Queue, so nothing waits here.</span
                >
                <a
                    v-if="party.canManage"
                    :href="`/parties/${party.code}/edit`"
                    class="btn btn-warning"
                    data-test="turn-on"
                    >Turn on approval</a
                >
            </div>
            <SearchBar
                v-model="search"
                placeholder="Search tracks, artists, people… or try reason:rule"
                label="Search Flagged Requests"
                :result-count="visible.length"
                noun="Flagged Requests"
                :chips="chips"
                @toggle-chip="toggleChip"
            />
            <EmptyState
                v-if="items.length === 0"
                icon="ti-check"
                all-clear
                title="Nothing waiting for approval"
                description="When a rule or AI review flags a Request, it waits here until someone approves or rejects it."
            />
            <EmptyState
                v-else-if="visible.length === 0"
                icon="ti-filter-off"
                title="No Flagged Requests match"
                description="Try a different search or clear the filters."
            />
            <div v-else class="card mp-bulk-card">
                <div v-if="selected.length > 0" class="mp-bulkbar" data-test="bulk-bar">
                    <strong data-test="bulk-count">{{ selected.length }} selected</strong>
                    <button type="button" class="btn btn-success" data-test="bulk-approve" @click="approveSelected">
                        <i class="icon ti ti-check" aria-hidden="true"></i>Approve {{ selected.length }}
                    </button>
                    <button
                        type="button"
                        class="btn btn-outline-danger"
                        data-test="bulk-reject"
                        @click="askRejectSelected"
                    >
                        <i class="icon ti ti-x" aria-hidden="true"></i>Reject {{ selected.length }}
                    </button>
                    <button type="button" class="btn btn-link ms-auto" data-test="bulk-clear" @click="selected = []">
                        Clear selection
                    </button>
                </div>
                <div class="mp-selectall">
                    <input
                        id="flagged-all"
                        type="checkbox"
                        class="form-check-input"
                        :checked="allSelected"
                        :aria-label="`Select all ${visible.length} Flagged Requests`"
                        data-test="select-all"
                        @change="toggleAll"
                    />
                    <label for="flagged-all" class="m-0">Select all {{ visible.length }}</label>
                    <span class="ms-auto small">Newest first</span>
                </div>
                <div class="list-group list-group-flush">
                    <div
                        v-for="item in visible"
                        :key="item.id"
                        class="list-group-item mp-flag-row"
                        :class="{'is-selected': selected.includes(item.id)}"
                        data-test="flagged-item"
                    >
                        <div class="row g-3 align-items-center">
                            <div class="col-auto">
                                <input
                                    type="checkbox"
                                    class="form-check-input"
                                    :checked="selected.includes(item.id)"
                                    :aria-label="`Select ${item.track.name}`"
                                    data-test="select"
                                    @change="toggleOne(item.id)"
                                />
                            </div>
                            <div class="col-auto">
                                <img
                                    v-if="item.track.album.image_url"
                                    class="mp-art"
                                    :src="item.track.album.image_url"
                                    alt=""
                                    width="64"
                                    height="64"
                                />
                            </div>
                            <div class="col" style="min-inline-size: 12rem">
                                <div class="mp-title">{{ item.track.name }}</div>
                                <div class="text-secondary">{{ item.track.artists.map((a) => a.name).join(', ') }}</div>
                                <div class="small text-secondary mt-1" data-test="flagged-requester">
                                    Requested by {{ item.requester.name }} &middot; {{ minutesAgo(item.flagged_at) }}
                                </div>
                                <div
                                    v-if="item.ai_review || item.ai_review_url"
                                    class="mp-reason"
                                    data-test="flagged-reason"
                                >
                                    <i class="icon ti ti-robot" aria-hidden="true"></i>
                                    <div>
                                        AI review<template v-if="item.ai_review?.criterion"
                                            >: <b>{{ item.ai_review.criterion }}</b></template
                                        >{{ confidenceText(item.ai_review?.confidence) }}.
                                        <q v-if="item.ai_review?.reason">{{ item.ai_review.reason }}</q>
                                        <a
                                            v-if="item.ai_review_url"
                                            :href="item.ai_review_url"
                                            class="ms-1"
                                            data-test="ai-review-link"
                                            >View AI review</a
                                        >
                                    </div>
                                </div>
                                <div v-else-if="item.reason" class="mp-reason" data-test="flagged-reason">
                                    <i class="icon ti ti-filter" aria-hidden="true"></i>
                                    <div>
                                        Rule: {{ item.reason }}
                                        <a v-if="item.rule_url" :href="item.rule_url" class="ms-1" data-test="rule-link"
                                            >View rule</a
                                        >
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-sm-auto">
                                <div class="mp-flag-actions">
                                    <button
                                        type="button"
                                        class="btn btn-success"
                                        data-test="approve"
                                        @click="approve(item)"
                                    >
                                        <i class="icon ti ti-check" aria-hidden="true"></i>Approve
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-outline-danger"
                                        data-test="reject"
                                        @click="askReject([item])"
                                    >
                                        <i class="icon ti ti-x" aria-hidden="true"></i>Reject
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <ConfirmDialog
            v-model="rejectOpen"
            :title="rejecting.length > 1 ? `Reject ${rejecting.length} requests` : 'Reject request'"
            :message="
                rejecting.length > 1
                    ? 'The requesters will be told their requests were rejected.'
                    : 'The requester will be told their request was rejected.'
            "
            confirm-label="Reject"
            @confirm="confirmReject"
        >
            <textarea
                v-model="rejectMessage"
                class="form-control mt-2"
                rows="2"
                placeholder="Message (optional)"
                data-test="reject-message"
            ></textarea>
        </ConfirmDialog>
    </AppLayout>
</template>
