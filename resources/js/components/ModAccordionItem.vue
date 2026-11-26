<script setup lang="ts">
const props = defineProps<{
    id: string;
    name: string;
    description?: string | null;
    open: boolean;
}>();

const emit = defineEmits<{'update:open': [value: boolean]}>();

function toggle(): void {
    emit('update:open', !props.open);
}
</script>

<template>
    <div class="accordion-item" data-test="mod">
        <div class="mod-head" :class="{'mod-open': open}">
            <div class="mod-text">
                <h2 class="accordion-header m-0">
                    <button
                        type="button"
                        class="mod-toggle"
                        :class="{collapsed: !open}"
                        :aria-expanded="open"
                        :aria-controls="`${id}-body`"
                        :aria-describedby="description ? `${id}-description` : undefined"
                        data-test="mod-toggle"
                        @click="toggle"
                    >
                        {{ name }}
                    </button>
                </h2>
                <p v-if="description" :id="`${id}-description`" class="mod-description" data-test="mod-description">
                    {{ description }}
                </p>
            </div>
            <div v-if="$slots.status" class="mod-status">
                <slot name="status" />
            </div>
            <svg
                class="mod-chevron"
                xmlns="http://www.w3.org/2000/svg"
                width="20"
                height="20"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
                data-test="mod-chevron"
            >
                <path d="M6 9l6 6l6 -6" />
            </svg>
        </div>
        <div :id="`${id}-body`" class="accordion-collapse collapse" :class="{show: open}">
            <div class="accordion-body border-top pt-3">
                <slot />
            </div>
        </div>
    </div>
</template>

<style scoped>
.mod-head {
    position: relative;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    grid-template-areas:
        'text chevron'
        'status status';
    align-items: center;
    gap: 0.25rem 1rem;
    padding: 1rem 1.25rem;
}

@media (min-width: 576px) {
    .mod-head {
        grid-template-columns: minmax(0, 1fr) auto auto;
        grid-template-areas: 'text status chevron';
        gap: 1rem 1.5rem;
    }
}

.mod-text {
    grid-area: text;
    min-width: 0;
}

.mod-toggle {
    padding: 0;
    border: 0;
    background: none;
    color: var(--tblr-body-color);
    font: inherit;
    font-weight: 600;
    text-align: start;
}

.mod-toggle::after {
    content: '';
    position: absolute;
    inset: 0;
}

.mod-toggle:focus-visible {
    outline: none;
}

.mod-toggle:focus-visible::after {
    outline: 2px solid var(--tblr-primary);
    outline-offset: -2px;
    border-radius: inherit;
}

.mod-description {
    max-width: 65ch;
    margin: 0.25rem 0 0;
    color: var(--tblr-secondary);
    font-size: 0.875rem;
    line-height: 1.5;
}

.mod-status {
    grid-area: status;
    position: relative;
    z-index: 1;
    justify-self: start;
}

@media (min-width: 576px) {
    .mod-status {
        justify-self: end;
    }
}

.mod-chevron {
    grid-area: chevron;
    color: var(--tblr-secondary);
    pointer-events: none;
    transition: transform 0.2s ease-out;
}

.mod-open .mod-chevron {
    transform: rotate(180deg);
}

@media (prefers-reduced-motion: reduce) {
    .mod-chevron {
        transition: none;
    }
}
</style>
