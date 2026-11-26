<script setup lang="ts">
import {computed, ref} from 'vue';
import {activateParty, approveFlagged, rejectFlagged, useRun} from '@/lib/dashboard';
import type {DashboardIssue, DashboardIssueCode, FlaggedRequest} from '@/types';

const props = defineProps<{
    code: string;
    issues: DashboardIssue[];
    flagged: FlaggedRequest[];
    flaggedTotal: number;
    flaggedUrl: string;
    canModerate: boolean;
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
};

const artists = (item: FlaggedRequest): string => item.track.artists.map((a) => a.name).join(', ');

async function act(action: () => Promise<unknown>): Promise<void> {
    busy.value = true;
    await run(action);
    busy.value = false;
}
</script>

<template>
    <div class="card mb-3 na-card" data-test="needs-attention">
        <div class="card-header">
            <h3 class="card-title">Needs attention</h3>
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
                        <button
                            type="button"
                            class="btn btn-outline-primary"
                            :disabled="busy"
                            data-test="issue-action"
                            @click="act(() => activateParty(code))"
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
                            >{{ flaggedTotal }} flagged request{{ flaggedTotal === 1 ? '' : 's' }}</span
                        >
                        <i class="ti text-secondary" :class="open ? 'ti-chevron-up' : 'ti-chevron-down'"></i>
                    </button>
                    <ul v-if="open" class="na-list">
                        <li v-for="item in flagged" :key="item.id" class="na-row" data-test="flagged-row">
                            <div class="na-text">
                                <div class="fw-medium">{{ item.track.name }}</div>
                                <div class="na-detail">
                                    {{ artists(item) }} · {{ item.requester.name }}
                                    <template v-if="item.ai_review_url">
                                        ·
                                        <a :href="item.ai_review_url" data-test="ai-review-link">AI review</a>
                                    </template>
                                </div>
                            </div>
                            <div v-if="canModerate" class="na-acts">
                                <button
                                    type="button"
                                    class="btn btn-success na-btn"
                                    title="Approve"
                                    :aria-label="`Approve ${item.track.name}`"
                                    :disabled="busy"
                                    data-test="approve"
                                    @click="act(() => approveFlagged(code, item.id))"
                                >
                                    <i class="ti ti-check"></i>
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-outline-danger na-btn"
                                    title="Reject"
                                    :aria-label="`Reject ${item.track.name}`"
                                    :disabled="busy"
                                    data-test="reject"
                                    @click="act(() => rejectFlagged(code, item.id))"
                                >
                                    <i class="ti ti-x"></i>
                                </button>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="card-footer py-2">
                <a :href="flaggedUrl" class="small">Flagged page <i class="ti ti-chevron-right"></i></a>
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
