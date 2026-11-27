<script setup lang="ts">
import {computed, ref} from 'vue';
import {activateParty, approveFlagged, rejectFlagged, startPlayback, useRun} from '@/lib/dashboard';
import type {DashboardIssue, DashboardIssueCode, FlaggedRequest} from '@/types';

const props = defineProps<{
    code: string;
    issues: DashboardIssue[];
    flagged: FlaggedRequest[];
    flaggedTotal: number;
    flaggedUrl: string;
    canModerate: boolean;
    webPlayerUrl?: string | null;
    relinkUrl?: string | null;
    deviceId?: string | null;
}>();

const run = useRun();
const open = ref(true);
const busy = ref(false);

const count = computed(() => props.issues.length + props.flaggedTotal);

const appearance: Record<DashboardIssueCode, {icon: string; tone: string}> = {
    relink_required: {icon: 'ti-link', tone: 'warning'},
    party_inactive: {icon: 'ti-power', tone: 'secondary'},
    player_stale: {icon: 'ti-antenna-bars-off', tone: 'warning'},
    premium_required: {icon: 'ti-brand-spotify', tone: 'danger'},
    no_device: {icon: 'ti-device-speaker-off', tone: 'danger'},
    outside: {icon: 'ti-player-play', tone: 'warning'},
};

function linkFor(issue: DashboardIssue): string | null {
    switch (issue.action?.kind) {
        case 'open_web_player':
            return props.webPlayerUrl ?? null;
        case 'relink':
            return props.relinkUrl ?? null;
        default:
            return null;
    }
}

function perform(issue: DashboardIssue): Promise<void> {
    switch (issue.action?.kind) {
        case 'start':
            return act(() => startPlayback(props.code, props.deviceId ?? null));
        case 'refresh_devices':
            return act(() => Promise.resolve());
        default:
            return act(() => activateParty(props.code));
    }
}

const artists = (item: FlaggedRequest): string => item.track.artists.map((a) => a.name).join(', ');

function whyFor(item: FlaggedRequest): string | null {
    const confidence = item.ai_review?.confidence;
    const parts = [
        item.reason,
        confidence === null || confidence === undefined ? null : `${Math.round(confidence * 100)}% confident`,
    ].filter((part): part is string => Boolean(part));
    return parts.length > 0 ? parts.join(', ') : null;
}

async function act(action: () => Promise<unknown>): Promise<void> {
    busy.value = true;
    await run(action);
    busy.value = false;
}
</script>

<template>
    <div class="card mb-3 na-card" data-test="needs-attention">
        <div class="card-header">
            <h2 class="card-title">Needs attention</h2>
            <div v-if="count === 0" class="card-actions text-success fw-medium" data-test="all-clear">
                <i class="ti ti-circle-check me-1"></i>All clear
            </div>
            <div v-else class="card-actions">
                <span class="badge bg-red text-red-fg" data-test="attention-count">{{ count }}</span>
            </div>
        </div>
        <template v-if="count > 0">
            <div class="na-body">
                <div v-for="issue in issues" :key="issue.code" class="na-row" data-test="issue-row">
                    <i
                        class="ti na-ico"
                        :class="[appearance[issue.code].icon, `text-${appearance[issue.code].tone}`]"
                        aria-hidden="true"
                    ></i>
                    <div class="na-text">
                        <div class="fw-medium">{{ issue.fact }}</div>
                        <div class="na-detail">{{ issue.detail }}</div>
                    </div>
                    <div v-if="issue.action" class="na-acts">
                        <a
                            v-if="linkFor(issue)"
                            :href="linkFor(issue) ?? undefined"
                            class="btn btn-outline-primary"
                            data-test="issue-action"
                            >{{ issue.action.label }}</a
                        >
                        <button
                            v-else
                            type="button"
                            class="btn btn-outline-primary"
                            :disabled="busy"
                            data-test="issue-action"
                            @click="perform(issue)"
                        >
                            {{ issue.action.label }}
                        </button>
                    </div>
                </div>
                <div v-if="flaggedTotal > 0" class="na-group" data-test="flagged-group">
                    <button
                        type="button"
                        class="na-ghead"
                        :aria-expanded="open"
                        data-test="flagged-toggle"
                        @click="open = !open"
                    >
                        <i class="ti ti-flag na-ico text-danger" aria-hidden="true"></i>
                        <span class="fw-medium flex-grow-1 text-start"
                            >{{ flaggedTotal }} Flagged Request{{ flaggedTotal === 1 ? '' : 's' }} waiting for
                            approval</span
                        >
                        <i class="ti text-secondary" :class="open ? 'ti-chevron-up' : 'ti-chevron-down'"></i>
                    </button>
                    <ul v-if="open" class="na-list">
                        <li v-for="item in flagged" :key="item.id" class="na-row" data-test="flagged-row">
                            <div class="na-text">
                                <div class="fw-medium">{{ item.track.name }}</div>
                                <div class="na-detail">
                                    {{ artists(item) }} · {{ item.requester.name }}
                                    <template v-if="whyFor(item)">
                                        · <span data-test="flagged-why">{{ whyFor(item) }}</span>
                                    </template>
                                    <template v-if="item.ai_review_url">
                                        ·
                                        <a :href="item.ai_review_url" data-test="ai-review-link">AI review</a>
                                    </template>
                                </div>
                            </div>
                            <div v-if="canModerate" class="na-acts">
                                <button
                                    type="button"
                                    class="btn btn-outline-success na-btn lab"
                                    title="Approve"
                                    :aria-label="`Approve ${item.track.name}`"
                                    :disabled="busy"
                                    data-test="approve"
                                    @click="act(() => approveFlagged(code, item.id))"
                                >
                                    <i class="ti ti-check"></i>Approve
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-outline-danger na-btn lab"
                                    title="Reject"
                                    :aria-label="`Reject ${item.track.name}`"
                                    :disabled="busy"
                                    data-test="reject"
                                    @click="act(() => rejectFlagged(code, item.id))"
                                >
                                    <i class="ti ti-x"></i>Reject
                                </button>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="card-footer py-2">
                <a :href="flaggedUrl" class="small">Open Flagged Requests <i class="ti ti-chevron-right"></i></a>
            </div>
        </template>
    </div>
</template>

<style scoped>
.na-body {
    padding: 4px 0;
}
.na-row {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    column-gap: 12px;
    align-items: center;
    padding: 10px 20px;
}
.na-row + .na-row,
.na-group,
.na-row + .na-group {
    border-top: 1px solid var(--tblr-border-color);
}
.na-body > :first-child {
    border-top: 0;
}
.na-ico {
    font-size: 1.35rem;
    width: 1.5rem;
    text-align: center;
}
.na-text {
    min-width: 0;
    overflow-wrap: anywhere;
    line-height: 1.35;
}
.na-detail {
    font-size: 0.85rem;
    color: var(--tblr-secondary);
}
.na-acts {
    display: flex;
    gap: 8px;
}
.na-acts .btn {
    min-height: 44px;
    white-space: nowrap;
}
.na-btn {
    width: 44px;
    height: 44px;
    padding: 0;
}
.na-btn .ti {
    font-size: 1.35rem;
}
.na-btn.lab {
    width: auto;
    min-width: 124px;
    padding: 0 14px;
    gap: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
}
.na-btn.lab .ti {
    font-size: 1.2rem;
}
.na-ghead {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    padding: 12px 20px;
    background: none;
    border: 0;
    color: inherit;
    font: inherit;
    cursor: pointer;
}
.na-ghead:hover {
    background: var(--tblr-bg-surface-secondary);
}
.na-list {
    list-style: none;
    margin: 0;
    padding: 0 0 4px;
}
.na-list .na-row {
    grid-template-columns: minmax(0, 1fr) auto;
    padding-left: 56px;
    border-top: 0;
}
</style>
