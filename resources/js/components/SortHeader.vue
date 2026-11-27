<script setup lang="ts">
import {Link} from '@inertiajs/vue3';
import {computed} from 'vue';

const props = withDefaults(
    defineProps<{
        title: string;
        field: string;
        order?: string | null;
        orderDirection?: 'asc' | 'desc' | null;
        direction?: 'asc' | 'desc';
    }>(),
    {order: null, orderDirection: 'asc', direction: 'asc'},
);

const active = computed(() => props.order === props.field);

const href = computed(() => {
    const url = new URL(window.location.href);
    url.searchParams.set('order', props.field);
    url.searchParams.set(
        'order_direction',
        active.value ? (props.orderDirection === 'asc' ? 'desc' : 'asc') : props.direction,
    );
    url.searchParams.delete('page');
    return `${url.pathname}${url.search}`;
});
</script>

<template>
    <Link :href="href" class="mp-sort">{{ title }}</Link>
    <i v-if="active" class="icon ti" :class="`ti-caret-${orderDirection === 'asc' ? 'up' : 'down'}-filled`"></i>
</template>
