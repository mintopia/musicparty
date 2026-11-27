<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import {moderationCrumbs} from '@/lib/breadcrumbs';
import {Head, Link, router} from '@inertiajs/vue3';
import {computed, reactive, ref} from 'vue';
import {composeSearch, resolveSearch, setSingleToken} from '@/utils/searchFilters';
import type {TokenSpec} from '@/utils/searchFilters';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHead from '@/components/PageHead.vue';
import Pagination from '@/components/Pagination.vue';
import SearchBar from '@/components/SearchBar.vue';
import type {SearchChip} from '@/components/SearchBar.vue';
import ModerationTabs from '@/components/ModerationTabs.vue';
import PartyPlayer from '@/components/PartyPlayer.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type {LegacyPartyState, ModerationTabsProps, Paginator} from '@/types';

interface ModerationRow {
    id: number;
    enabled: boolean;
    regex: boolean;
    type_name: string | null;
    action: string;
    value: string;
    notes: string | null;
}

const props = defineProps<{
    party: {code: string; name: string; canManage: boolean; ownerName?: string | null};
    playerState: LegacyPartyState;
    moderations: Paginator<ModerationRow> & {total: number; from?: number | null; to?: number | null};
    moderationTabs: ModerationTabsProps;
    filters: {q: string; state: string; action: string; pattern: boolean};
    counts: {total: number; on: number; off: number; flag: number; reject: number; pattern: number};
}>();

const spec: TokenSpec = {state: ['on', 'off'], action: ['flag', 'reject'], match: ['pattern']};
const form = reactive({
    q: composeSearch(props.filters.q, {
        state: props.filters.state,
        action: props.filters.action,
        match: props.filters.pattern ? 'pattern' : '',
    }),
});
const listUrl = `/parties/${props.party.code}/moderation`;
const filtered = computed(
    () => props.filters.q !== '' || props.filters.state !== '' || props.filters.action !== '' || props.filters.pattern,
);
const resolved = computed(() => resolveSearch(form.q, spec));

const chips = computed<SearchChip[]>(() => [
    {key: 'state:on', label: 'On', count: props.counts.on, pressed: resolved.value.filters.state === 'on'},
    {key: 'state:off', label: 'Off', count: props.counts.off, pressed: resolved.value.filters.state === 'off'},
    {key: 'action:flag', label: 'Flag', count: props.counts.flag, pressed: resolved.value.filters.action === 'flag'},
    {
        key: 'action:reject',
        label: 'Reject',
        count: props.counts.reject,
        pressed: resolved.value.filters.action === 'reject',
    },
    {
        key: 'match:pattern',
        label: 'Pattern',
        count: props.counts.pattern,
        pressed: resolved.value.filters.match === 'pattern',
    },
]);

const activeSummary = computed(() => {
    const parts: string[] = [];
    if (props.filters.q !== '') {
        parts.push(`\u201c${props.filters.q}\u201d`);
    }
    for (const chip of chips.value) {
        if (chip.pressed) {
            parts.push(chip.label);
        }
    }
    return parts.join(', ');
});

const pendingDelete = ref<ModerationRow | null>(null);
const deleteOpen = computed({
    get: () => pendingDelete.value !== null,
    set: (open: boolean) => {
        if (!open) {
            pendingDelete.value = null;
        }
    },
});
const deleteMessage = computed(() => {
    const rule = pendingDelete.value;
    if (rule === null) {
        return '';
    }
    return `The rule ${rule.type_name} is ${rule.value} will stop ${rule.action === 'flag' ? 'flagging' : 'rejecting'} Requests. Requests already waiting in Flagged Requests stay there.`;
});

function confirmDelete(): void {
    if (pendingDelete.value !== null) {
        router.delete(`${listUrl}/${pendingDelete.value.id}`);
    }
}

function apply(): void {
    const {text, filters} = resolveSearch(form.q, spec);
    const query: Record<string, string> = {};
    if (text.trim() !== '') {
        query.q = text.trim();
    }
    if (filters.state) {
        query.state = filters.state;
    }
    if (filters.action) {
        query.action = filters.action;
    }
    if (filters.match === 'pattern') {
        query.pattern = '1';
    }
    router.get(listUrl, query, {preserveState: true, replace: true});
}

function setQuery(value: string): void {
    form.q = value;
    apply();
}

function toggleChip(key: string): void {
    const [field, value] = key.split(':') as [string, string];
    form.q = setSingleToken(form.q, spec, field, value);
    apply();
}
</script>

<template>
    <AppLayout :title="`Moderation - ${party.name}`">
        <template #breadcrumbs>
            <Breadcrumbs :items="[...moderationCrumbs(party), {label: 'Rules'}]" />
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
            <ModerationTabs :code="party.code" :tabs="moderationTabs" active="rules" />
            <div v-if="!party.canManage" class="text-secondary mb-3" data-test="rules-readonly">
                <i class="ti ti-lock me-1" aria-hidden="true"></i>Rules are read-only for moderators. Only
                {{ party.ownerName ?? 'the owner' }} can add, edit or delete them.
            </div>
            <SearchBar
                v-if="counts.total > 0 || filtered"
                :model-value="form.q"
                placeholder="Search rules… or try action:flag"
                label="Search Rules"
                :result-count="moderations.total"
                :total="counts.total"
                noun="Rules"
                :chips="chips"
                @update:model-value="setQuery"
                @submit="setQuery"
                @toggle-chip="toggleChip"
            >
                <Link v-if="party.canManage" :href="`${listUrl}/create`" class="btn btn-primary d-print-none">
                    <i class="icon ti ti-plus"></i>
                    Add rule
                </Link>
            </SearchBar>

            <EmptyState
                v-if="moderations.data.length === 0 && filtered"
                icon="ti-filter-off"
                title="No matching rules"
                :description="`Nothing matches ${activeSummary}.`"
            >
                <template #title
                    >No rules match <code>{{ form.q.trim() }}</code></template
                >
                <Link :href="listUrl" class="btn btn-primary" data-test="clear-filters">Clear filters</Link>
            </EmptyState>
            <EmptyState
                v-else-if="moderations.data.length === 0"
                icon="ti-filter-off"
                title="No moderation rules"
                description="Rules block or flag tracks by artist, album, title or pattern before they reach the queue."
            >
                <Link v-if="party.canManage" :href="`${listUrl}/create`" class="btn btn-primary">
                    <i class="icon ti ti-plus"></i>
                    Add rule
                </Link>
            </EmptyState>
            <div v-else class="card">
                <div class="table-responsive d-none d-md-block">
                    <table class="table table-hover table-vcenter text-nowrap card-table mp-rules-table">
                        <thead>
                            <tr>
                                <th>On</th>
                                <th>Type</th>
                                <th>Value</th>
                                <th>Action</th>
                                <th>Match</th>
                                <th>Notes</th>
                                <th v-if="party.canManage" class="w-1"><span class="visually-hidden">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="moderation in moderations.data" :key="moderation.id">
                                <td>
                                    <span
                                        class="status-dot me-2"
                                        :class="moderation.enabled ? 'status-green' : 'status-secondary'"
                                    ></span>
                                    {{ moderation.enabled ? 'On' : 'Off' }}
                                </td>
                                <td>{{ moderation.type_name }}</td>
                                <td>
                                    <code v-if="moderation.regex">{{ moderation.value }}</code>
                                    <template v-else>{{ moderation.value }}</template>
                                </td>
                                <td>
                                    <span v-if="moderation.action === 'flag'" class="badge bg-yellow-lt">
                                        <i class="icon ti ti-flag me-1" aria-hidden="true"></i>Flag
                                    </span>
                                    <span v-else class="badge bg-red-lt">
                                        <i class="icon ti ti-ban me-1" aria-hidden="true"></i>Reject
                                    </span>
                                </td>
                                <td>{{ moderation.regex ? 'Pattern' : 'Exact' }}</td>
                                <td class="text-secondary">{{ moderation.notes || '-' }}</td>
                                <td v-if="party.canManage" class="text-end text-nowrap">
                                    <Link
                                        :href="`${listUrl}/${moderation.id}/edit`"
                                        class="btn btn-icon btn-ghost-secondary"
                                        :aria-label="`Edit rule ${moderation.id}`"
                                    >
                                        <i class="icon ti ti-pencil" aria-hidden="true"></i>
                                    </Link>
                                    <button
                                        type="button"
                                        class="btn btn-icon btn-ghost-secondary"
                                        :aria-label="`Delete rule ${moderation.id}`"
                                        data-test="delete-rule"
                                        @click="pendingDelete = moderation"
                                    >
                                        <i class="icon ti ti-trash" aria-hidden="true"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="list-group list-group-flush d-md-none" data-test="rules-cards">
                    <div v-for="moderation in moderations.data" :key="moderation.id" class="list-group-item">
                        <div class="d-flex gap-3 align-items-start">
                            <div class="flex-fill" style="min-inline-size: 0">
                                <div class="small text-secondary">
                                    {{ moderation.type_name
                                    }}<template v-if="moderation.regex"> &middot; pattern</template>
                                </div>
                                <div class="fw-medium text-break">
                                    <code v-if="moderation.regex">{{ moderation.value }}</code>
                                    <template v-else>{{ moderation.value }}</template>
                                </div>
                                <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                                    <span v-if="moderation.action === 'flag'" class="badge bg-yellow-lt">Flag</span>
                                    <span v-else class="badge bg-red-lt">Reject</span>
                                    <span class="small">
                                        <span
                                            class="status-dot me-1"
                                            :class="moderation.enabled ? 'status-green' : 'status-secondary'"
                                        ></span
                                        >{{ moderation.enabled ? 'On' : 'Off' }}
                                    </span>
                                </div>
                                <div v-if="moderation.notes" class="text-secondary mt-2">{{ moderation.notes }}</div>
                            </div>
                            <Link
                                v-if="party.canManage"
                                :href="`${listUrl}/${moderation.id}/edit`"
                                class="btn btn-icon btn-ghost-secondary"
                                :aria-label="`Edit rule ${moderation.id}`"
                            >
                                <i class="icon ti ti-pencil" aria-hidden="true"></i>
                            </Link>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center">
                    <span class="text-secondary" data-test="rules-showing"
                        >Showing {{ moderations.from ?? 0 }} to {{ moderations.to ?? 0 }} of
                        {{ moderations.total }} Rules</span
                    >
                    <Pagination v-if="moderations.last_page > 1" class="ms-auto" :paginator="moderations" />
                </div>
            </div>
        </div>
        <ConfirmDialog
            v-model="deleteOpen"
            title="Delete this rule?"
            :message="deleteMessage"
            confirm-label="Delete rule"
            cancel-label="Keep rule"
            @confirm="confirmDelete"
        />
    </AppLayout>
</template>
