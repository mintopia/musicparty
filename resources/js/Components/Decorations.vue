<script setup>
import {computed} from 'vue';
import Icon from './Icon.vue';

const props = defineProps({decorations: {type: Array, default: () => []}});

const base = 'inline-flex max-w-full items-center gap-1 rounded px-2 py-0.5 text-xs font-medium';

const solid = {
    accent: 'bg-accent text-white',
    success: 'bg-emerald-600 text-white',
    warning: 'bg-amber-500 text-black',
    danger: 'bg-danger text-white',
    info: 'bg-sky-600 text-white',
    muted: 'bg-muted text-white',
};
const soft = {
    accent: 'bg-accent/15 text-accent',
    success: 'bg-emerald-500/15 text-emerald-600',
    warning: 'bg-amber-500/15 text-amber-600',
    danger: 'bg-danger/15 text-danger',
    info: 'bg-sky-500/15 text-sky-600',
    muted: 'bg-muted/15 text-muted',
};
const outline = {
    accent: 'border border-accent text-accent',
    success: 'border border-emerald-600 text-emerald-600',
    warning: 'border border-amber-500 text-amber-600',
    danger: 'border border-danger text-danger',
    info: 'border border-sky-600 text-sky-600',
    muted: 'border border-muted text-muted',
};
const classMaps = {solid, soft, outline};
const icons = ['star', 'flame', 'shield', 'clock', 'gift', 'snowflake', 'alert', 'check'];

const text = (value) => (typeof value === 'string' && value.trim() !== '' ? value : null);

const items = computed(() => {
    const list = Array.isArray(props.decorations) ? props.decorations : [];
    return list.flatMap((decoration, index) => {
        const classes = classMaps[decoration?.variant]?.[decoration?.accent];
        if (!classes) {
            return [];
        }
        const badge = text(decoration.badge);
        const label = text(decoration.label);
        const icon = icons.includes(decoration.icon) ? decoration.icon : null;
        if (!badge && !label && !icon) {
            return [];
        }
        return [{key: `${decoration.mod_id ?? ''}-${index}`, badge, label, icon, classes: `${base} ${classes}`}];
    });
});
</script>

<template>
    <div v-if="items.length > 0" data-testid="decorations" class="flex flex-wrap items-center gap-1">
        <span v-for="item in items" :key="item.key" data-testid="decoration" :class="item.classes">
            <Icon v-if="item.icon" :name="item.icon" class="h-3.5 w-3.5" />
            <span v-if="item.badge" data-testid="decoration-badge">{{ item.badge }}</span>
            <span v-if="item.label" data-testid="decoration-label" class="truncate">{{ item.label }}</span>
        </span>
    </div>
</template>
