<script setup>
import {Link, usePage} from '@inertiajs/vue3';
import {computed} from 'vue';
import Icon from '../Icon.vue';

const props = defineProps({
    column: {type: Object, required: true},
    defaultOrder: {type: String, default: null},
    orderParam: {type: String, default: 'order'},
    directionParam: {type: String, default: 'order_direction'},
});

const page = usePage();
const parsed = computed(() => {
    const [path, query = ''] = page.url.split('?');
    return {path, params: new URLSearchParams(query)};
});
const current = computed(() => parsed.value.params.get(props.orderParam) ?? props.defaultOrder);
const direction = computed(() => parsed.value.params.get(props.directionParam) ?? (current.value === props.defaultOrder ? (props.column.defaultDirection ?? 'asc') : 'asc'));
const active = computed(() => current.value === props.column.key);
const href = computed(() => {
    const params = new URLSearchParams(parsed.value.params);
    params.delete('page');
    params.set(props.orderParam, props.column.key);
    params.set(props.directionParam, active.value && direction.value === 'asc' ? 'desc' : 'asc');
    return `${parsed.value.path}?${params.toString()}`;
});
</script>

<template>
    <Link :href="href" class="inline-flex items-center gap-1 hover:text-primary" :data-sort="column.key">
        {{ column.label }}
        <Icon v-if="active" :name="direction === 'asc' ? 'caretUp' : 'caretDown'" class="!h-4 !w-4" />
    </Link>
</template>
