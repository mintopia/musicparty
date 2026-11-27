<script setup lang="ts">
import {computed, useSlots} from 'vue';
import type {ModStat, ModStatus} from '@/lib/mods';

const props = defineProps<{
    id: string;
    name: string;
    description?: string | null;
    icon: string;
    stats?: ModStat[];
    status?: ModStatus;
    note?: string;
}>();

const enabled = defineModel<boolean | undefined>('enabled', {default: undefined});
const open = defineModel<boolean>('open', {default: true});

const slots = useSlots();

const switchable = computed(() => enabled.value !== undefined);
const isOff = computed(() => enabled.value === false);
const hasBody = computed(() => !isOff.value && !!slots.default);
const pill = computed<ModStatus | undefined>(() => {
    if (props.status) {
        return props.status;
    }
    if (enabled.value === undefined) {
        return undefined;
    }
    return enabled.value ? {label: 'On', tone: 'success'} : {label: 'Off', tone: 'muted'};
});
</script>

<template>
    <div :id="id" class="mod-card" :class="{'mod-card-off': isOff}" data-test="mod">
        <div class="mod-card-head">
            <span class="mod-card-icon" :class="{'mod-card-icon-off': isOff}" aria-hidden="true">
                <i class="ti" :class="icon"></i>
            </span>
            <div class="mod-card-text">
                <div class="mod-card-title">
                    <label v-if="switchable" class="mod-card-name" :for="`${id}-enabled`">{{ name }}</label>
                    <h3 v-else class="mod-card-name">{{ name }}</h3>
                    <span
                        v-if="pill"
                        class="mod-card-pill"
                        :class="`mod-card-pill-${pill.tone}`"
                        data-test="mod-status"
                    >
                        <span class="mod-card-dot" aria-hidden="true"></span>{{ pill.label }}
                    </span>
                </div>
                <p v-if="description" class="mod-card-description" data-test="mod-description">{{ description }}</p>
                <p v-if="note" class="mod-card-note" data-test="mod-note">{{ note }}</p>
            </div>
            <div class="mod-card-actions">
                <button
                    v-if="hasBody"
                    type="button"
                    class="btn btn-sm mod-card-toggle"
                    :aria-expanded="open"
                    :aria-controls="`${id}-body`"
                    data-test="mod-toggle"
                    @click="open = !open"
                >
                    <i class="ti" :class="open ? 'ti-chevron-up' : 'ti-chevron-down'" aria-hidden="true"></i>
                    Settings
                </button>
                <span v-if="switchable" class="form-check form-switch mb-0 ps-0 min-height-0 mod-card-switch">
                    <input
                        :id="`${id}-enabled`"
                        v-model="enabled"
                        type="checkbox"
                        role="switch"
                        class="form-check-input m-0"
                        :aria-label="`Enable ${name}`"
                        data-test="mod-enabled"
                    />
                </span>
            </div>
        </div>
        <dl v-if="stats?.length && !isOff" class="mod-card-stats" data-test="mod-stats">
            <div v-for="stat in stats" :key="stat.label" class="mod-card-stat" data-test="mod-stat">
                <dt>{{ stat.label }}</dt>
                <dd :class="stat.tone ? `mod-card-stat-${stat.tone}` : undefined">{{ stat.value }}</dd>
            </div>
        </dl>
        <div v-if="hasBody" v-show="open" :id="`${id}-body`" class="mod-card-body" data-test="mod-body">
            <slot />
        </div>
    </div>
</template>

<style scoped>
.mod-card {
    margin-top: 0.75rem;
    border: 1px solid var(--tblr-border-color);
    border-radius: var(--tblr-border-radius-lg, 0.5rem);
    background: var(--tblr-bg-surface);
    overflow: hidden;
}

.mod-card-head {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: start;
    gap: 0.75rem 1rem;
    padding: 1rem 1.25rem;
}

.mod-card-off .mod-card-head {
    align-items: center;
    padding-block: 0.75rem;
}

.mod-card-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    inline-size: 2.5rem;
    block-size: 2.5rem;
    border-radius: var(--tblr-border-radius);
    background: var(--tblr-primary);
    color: var(--tblr-primary-fg, #fff);
    font-size: 1.25rem;
}

.mod-card-icon-off {
    background: var(--tblr-bg-surface-secondary);
    color: var(--tblr-secondary);
}

.mod-card-text {
    min-width: 0;
}

.mod-card-title {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.25rem 0.5rem;
}

.mod-card-name {
    margin: 0;
    font-size: 1.0625rem;
    font-weight: 600;
    color: var(--tblr-body-color);
}

.mod-card-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.0625rem 0.5rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
}

.mod-card-dot {
    inline-size: 0.4375rem;
    block-size: 0.4375rem;
    border-radius: 50%;
    background: currentColor;
}

.mod-card-pill-success {
    background: var(--tblr-success-lt);
    color: var(--tblr-success);
}

.mod-card-pill-warning {
    background: var(--tblr-warning-lt);
    color: color-mix(in oklab, var(--tblr-warning) 65%, var(--tblr-body-color));
}

.mod-card-pill-muted {
    background: var(--tblr-bg-surface-secondary);
    color: var(--tblr-secondary);
}

.mod-card-description {
    max-width: 65ch;
    margin: 0.25rem 0 0;
    color: var(--tblr-secondary);
    font-size: 0.9375rem;
    line-height: 1.5;
}

.mod-card-off .mod-card-description {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.mod-card-note {
    margin: 0.375rem 0 0;
    color: var(--tblr-secondary);
    font-size: 0.8125rem;
}

.mod-card-actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.mod-card-toggle {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    border: 1px solid var(--tblr-border-color);
    background: var(--tblr-bg-surface);
    color: var(--tblr-body-color);
}

.mod-card-switch .form-check-input {
    inline-size: 2.5rem;
    block-size: 1.375rem;
    float: none;
}

.mod-card-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    margin: 0;
    border-block: 1px solid var(--tblr-border-color);
    background: var(--tblr-bg-surface-secondary);
}

.mod-card-stat {
    min-width: 0;
    padding: 0.625rem 1.25rem;
}

.mod-card-stat + .mod-card-stat {
    border-inline-start: 1px solid var(--tblr-border-color);
}

.mod-card-stat dt {
    color: var(--tblr-secondary);
    font-size: 0.6875rem;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.mod-card-stat dd {
    margin: 0;
    overflow: hidden;
    font-weight: 600;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.mod-card-stat-success {
    color: var(--tblr-success);
}

.mod-card-stat-warning {
    color: color-mix(in oklab, var(--tblr-warning) 65%, var(--tblr-body-color));
}

@media (max-width: 767.98px) {
    .mod-card-head {
        grid-template-columns: auto minmax(0, 1fr);
        padding: 0.875rem 1rem;
    }

    .mod-card-actions {
        grid-column: 1 / -1;
        justify-content: space-between;
    }

    .mod-card-toggle {
        min-block-size: 44px;
    }

    .mod-card-switch {
        display: inline-flex;
        align-items: center;
        min-block-size: 44px;
    }

    .mod-card-stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .mod-card-stat {
        padding-inline: 1rem;
    }

    .mod-card-stat:nth-child(odd) {
        border-inline-start: 0;
    }

    .mod-card-stat:nth-child(n + 3) {
        border-block-start: 1px solid var(--tblr-border-color);
    }
}
</style>
