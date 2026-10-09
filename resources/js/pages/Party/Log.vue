<script setup>
import {Head, Link} from '@inertiajs/vue3';
import {computed} from 'vue';

const props = defineProps({
    party: {type: Object, required: true},
    entries: {type: Object, required: true},
});

const rows = computed(() => props.entries?.data ?? []);
const prevUrl = computed(() => props.entries?.links?.prev ?? null);
const nextUrl = computed(() => props.entries?.links?.next ?? null);

const formatValue = (value) => (value !== null && typeof value === 'object' ? JSON.stringify(value) : String(value));

const describe = (entry) => {
    switch (entry.action) {
        case 'party.created':
            return 'Party created';
        case 'party.settings_changed': {
            const label = `Changed setting ${entry.subject ?? ''}`.trim();
            if (entry.details && ('old' in entry.details || 'new' in entry.details)) {
                return `${label}: ${formatValue(entry.details.old ?? '')} -> ${formatValue(entry.details.new ?? '')}`;
            }
            return label;
        }
        default:
            return entry.action;
    }
};

const formatTime = (iso) => {
    const date = new Date(iso);
    return Number.isNaN(date.getTime()) ? iso : date.toLocaleString();
};
</script>

<template>
    <Head :title="`Party Log - ${party.name}`" />
    <div class="flex flex-col gap-4 px-4 pb-4 pt-6">
        <header class="flex flex-wrap items-center justify-between gap-2 rounded border border-border bg-surface px-5 py-5">
            <h1 class="text-xl font-semibold">Party Log</h1>
            <Link
                :href="`/parties/${party.code}`"
                class="flex min-h-11 items-center rounded border border-border px-3 text-sm hover:text-primary"
            >Back to {{ party.name }}</Link>
        </header>

        <section class="rounded border border-border bg-surface px-5 py-5">
            <p v-if="rows.length === 0" data-testid="log-empty" class="text-sm text-muted">No log entries yet.</p>
            <ul v-else class="flex flex-col divide-y divide-border">
                <li
                    v-for="entry in rows"
                    :key="entry.id"
                    data-testid="log-entry"
                    class="flex flex-col gap-1 py-3 first:pt-0 last:pb-0"
                >
                    <span class="text-sm">{{ describe(entry) }}</span>
                    <span class="flex flex-wrap items-center gap-2 text-xs text-muted">
                        <span v-if="entry.actor">{{ entry.actor }}</span>
                        <span
                            v-if="entry.actor_kind === 'system'"
                            class="rounded border border-border px-2 py-0.5 text-xs font-medium"
                        >system</span>
                        <time :datetime="entry.created_at">{{ formatTime(entry.created_at) }}</time>
                    </span>
                </li>
            </ul>
        </section>

        <nav v-if="prevUrl || nextUrl" class="flex items-center justify-between gap-2" aria-label="Pagination">
            <Link
                v-if="prevUrl"
                :href="prevUrl"
                class="flex min-h-11 items-center rounded border border-border px-3 text-sm hover:text-primary"
            >Previous</Link>
            <span v-else />
            <Link
                v-if="nextUrl"
                :href="nextUrl"
                class="flex min-h-11 items-center rounded border border-border px-3 text-sm hover:text-primary"
            >Next</Link>
        </nav>
    </div>
</template>
