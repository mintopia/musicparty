<script setup>
import {Link} from '@inertiajs/vue3';
import {computed} from 'vue';

const props = defineProps({paginator: {type: Object, required: true}});

const meta = computed(() => props.paginator.meta ?? props.paginator);
const links = computed(() => (meta.value.links ?? []).map((link) => ({
    ...link,
    text: /previous/i.test(link.label) ? '‹' : /next/i.test(link.label) ? '›' : link.label,
    ariaLabel: /previous/i.test(link.label) ? 'Previous' : /next/i.test(link.label) ? 'Next' : undefined,
})));
const total = computed(() => meta.value.total ?? props.paginator.data?.length ?? 0);
const from = computed(() => meta.value.from ?? (total.value ? 1 : 0));
const to = computed(() => meta.value.to ?? props.paginator.data?.length ?? 0);
const multiPage = computed(() => (meta.value.last_page ?? 1) > 1);
</script>

<template>
    <div class="flex items-center justify-between border-t border-border px-5 py-4 text-sm text-muted">
        <p data-testid="pagination-summary">Showing {{ from }} to {{ to }} of {{ total }} entries</p>
        <nav v-if="multiPage" aria-label="Pagination" class="flex items-center gap-1">
            <template v-for="(link, index) in links" :key="index">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    :aria-current="link.active ? 'page' : undefined"
                    :aria-label="link.ariaLabel"
                    class="min-w-8 rounded px-2 py-1 text-center"
                    :class="link.active ? 'bg-primary text-white' : 'text-text hover:bg-background'"
                >{{ link.text }}</Link>
                <span
                    v-else
                    class="min-w-8 px-2 py-1 text-center"
                    :class="link.active ? 'rounded bg-primary text-white' : 'opacity-40'"
                    :aria-current="link.active ? 'page' : undefined"
                    :aria-hidden="link.active ? undefined : 'true'"
                >{{ link.text }}</span>
            </template>
        </nav>
    </div>
</template>
