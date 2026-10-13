<script setup lang="ts">
import {Link} from '@inertiajs/vue3';
import type {Paginator} from '@/types';

defineProps<{paginator: Pick<Paginator<unknown>, 'links' | 'last_page'>}>();

const labelText = (label: string): string => label.replace(/&laquo;|&raquo;/g, '').replace(/<[^>]*>/g, '').trim();
</script>

<template>
    <nav v-if="paginator.last_page > 1" aria-label="Pagination">
        <ul class="pagination mb-0">
            <li
                v-for="(link, index) in paginator.links"
                :key="index"
                class="page-item"
                :class="{active: link.active, disabled: link.url === null}"
            >
                <Link v-if="link.url !== null" class="page-link" :href="link.url" preserve-scroll>
                    {{ labelText(link.label) || (index === 0 ? 'Previous' : 'Next') }}
                </Link>
                <span v-else class="page-link">{{ labelText(link.label) || (index === 0 ? 'Previous' : 'Next') }}</span>
            </li>
        </ul>
    </nav>
</template>
