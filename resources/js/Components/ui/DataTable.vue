<script setup>
import SortHeader from './SortHeader.vue';

defineProps({
    columns: {type: Array, required: true},
    rows: {type: Array, required: true},
    rowKey: {type: String, default: 'id'},
    rowAttrs: {type: Function, default: () => ({})},
    defaultOrder: {type: String, default: null},
    orderParam: {type: String, default: 'order'},
    directionParam: {type: String, default: 'order_direction'},
    emptyText: {type: String, default: 'No results.'},
    caption: {type: String, default: null},
});
</script>

<template>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <caption v-if="caption" class="sr-only">{{ caption }}</caption>
            <thead>
                <tr class="bg-background text-xs uppercase tracking-wide text-muted">
                    <th v-for="column in columns" :key="column.key" scope="col" class="px-5 py-3 font-semibold" :class="column.class">
                        <SortHeader v-if="column.sortable" :column="column" :default-order="defaultOrder" :order-param="orderParam" :direction-param="directionParam" />
                        <template v-else>{{ column.label }}</template>
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(row, index) in rows" :key="row[rowKey]" v-bind="rowAttrs(row)" class="border-t border-border">
                    <slot name="row" :row="row" :index="index" />
                </tr>
                <tr v-if="!rows.length" class="border-t border-border">
                    <td :colspan="columns.length" class="px-5 py-4 text-muted"><slot name="empty">{{ emptyText }}</slot></td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
