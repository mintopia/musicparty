<script setup>
defineProps({
    flush: {type: Boolean, default: false},
    status: {type: String, default: null},
});
</script>

<template>
    <section
        class="overflow-hidden rounded border border-border bg-surface"
        :class="status === 'danger' ? 'border-t-[3px] border-t-danger' : ''"
    >
        <header v-if="$slots.header || $slots.actions" class="flex items-center justify-between gap-3 border-b border-border px-5 py-4">
            <h2 class="text-base font-semibold"><slot name="header" /></h2>
            <div v-if="$slots.actions" class="flex items-center gap-2"><slot name="actions" /></div>
        </header>
        <div :class="flush ? '' : 'p-5'"><slot /></div>
        <footer v-if="$slots.footer" class="flex justify-end gap-2 border-t border-border bg-background px-5 py-3">
            <slot name="footer" />
        </footer>
    </section>
</template>
