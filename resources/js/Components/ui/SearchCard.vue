<script setup>
import {router, usePage} from '@inertiajs/vue3';
import {reactive} from 'vue';
import Button from './Button.vue';
import Card from './Card.vue';

const props = defineProps({
    fields: {type: Array, required: true},
    filters: {type: Object, default: () => ({})},
    title: {type: String, default: 'Search'},
});

const page = usePage();
const values = reactive(Object.fromEntries(props.fields.map((field) => [field.name, props.filters?.[field.name] ?? ''])));

const submit = () => {
    const [path, query = ''] = page.url.split('?');
    const current = new URLSearchParams(query);
    const data = {};
    for (const key of ['order', 'order_direction']) {
        if (current.get(key)) {
            data[key] = current.get(key);
        }
    }
    for (const field of props.fields) {
        if (values[field.name] !== '' && values[field.name] !== null) {
            data[field.name] = values[field.name];
        }
    }
    router.get(path, data, {preserveState: true});
};
</script>

<template>
    <form @submit.prevent="submit">
        <Card>
            <template #header>{{ title }}</template>
            <div v-for="field in fields" :key="field.name" class="mb-4 last:mb-0">
                <label :for="`search-${field.name}`" class="mb-1 block text-sm font-medium">{{ field.label }}</label>
                <input
                    :id="`search-${field.name}`"
                    v-model="values[field.name]"
                    type="text"
                    :name="field.name"
                    :placeholder="field.label"
                    class="w-full rounded border border-border bg-background px-3 py-2 text-sm"
                />
            </div>
            <template #footer><Button type="submit" data-action="search">Search</Button></template>
        </Card>
    </form>
</template>
