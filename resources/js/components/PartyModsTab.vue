<script setup lang="ts">
import {computed} from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import ModSettingField from '@/components/ModSettingField.vue';
import {groupMods} from '@/lib/mods';
import type {ModDraft} from '@/lib/mods';
import type {PartyMod} from '@/types';

const AI_REVIEW_MOD = 'ai-request-review';

const props = defineProps<{
    mods: PartyMod[];
    drafts: Record<string, ModDraft>;
    errors: Record<string, Record<string, string>>;
    userSearchUrl: string;
    holdsFlagged: boolean;
}>();

defineEmits<{'hold-flagged': []}>();

const groups = computed(() => groupMods(props.mods));

function errorFor(modCode: string, settingCode: string): string | undefined {
    return props.errors[modCode]?.[`mods.${modCode}.settings.${settingCode}`];
}

function generalErrors(modCode: string): string[] {
    const prefix = `mods.${modCode}.settings.`;
    return Object.entries(props.errors[modCode] ?? {})
        .filter(([key]) => !key.startsWith(prefix))
        .map(([, message]) => message);
}
</script>

<template>
    <EmptyState
        v-if="mods.length === 0"
        icon="ti-puzzle-off"
        title="No mods available"
        description="No mods are installed on this instance."
    />
    <div v-else>
        <section v-for="group in groups" :key="group.name" class="mod-group" :data-group="group.name">
            <h3 class="settings-heading">{{ group.name }}</h3>
            <div v-for="mod in group.mods" :id="`mod-${mod.code}`" :key="mod.code" class="mod-item" data-test="mod">
                <div class="mod-row">
                    <div class="mod-text">
                        <label class="mod-name" :for="`mod-${mod.code}-enabled`">{{ mod.name }}</label>
                        <p v-if="mod.description" class="mod-description" data-test="mod-description">
                            {{ mod.description }}
                        </p>
                    </div>
                    <span class="form-check form-switch mb-0 ps-0 min-height-0">
                        <input
                            :id="`mod-${mod.code}-enabled`"
                            v-model="drafts[mod.code]!.enabled"
                            type="checkbox"
                            role="switch"
                            class="form-check-input m-0"
                            :aria-label="`Enable ${mod.name}`"
                            data-test="mod-enabled"
                        />
                    </span>
                </div>
                <div v-if="drafts[mod.code]!.enabled" class="mod-settings" :data-test="`mod-settings-${mod.code}`">
                    <div
                        v-if="mod.code === AI_REVIEW_MOD && !holdsFlagged"
                        class="alert alert-warning py-2 mb-2"
                        role="status"
                        data-test="mod-notice"
                    >
                        Flagged Requests aren't held. They go straight to the Queue.
                        <button
                            type="button"
                            class="btn btn-link p-0 ms-1 align-baseline"
                            @click="$emit('hold-flagged')"
                        >
                            Turn on approval
                        </button>
                    </div>
                    <div
                        v-for="message in generalErrors(mod.code)"
                        :key="message"
                        class="text-danger small"
                        data-test="mod-error"
                    >
                        {{ message }}
                    </div>
                    <ModSettingField
                        v-for="setting in mod.settings"
                        :id="`mod-${mod.code}-${setting.code}`"
                        :key="setting.code"
                        v-model="drafts[mod.code]!.settings[setting.code]!"
                        row
                        :setting="setting"
                        :error="errorFor(mod.code, setting.code)"
                        :user-search-url="userSearchUrl"
                    />
                </div>
                <div v-else-if="errors[mod.code]" class="text-danger small mt-1" data-test="mod-error">
                    {{ Object.values(errors[mod.code]!)[0] }}
                </div>
            </div>
        </section>
    </div>
</template>

<style scoped>
.mod-group + .mod-group {
    margin-top: 2rem;
}

.settings-heading {
    margin: 0;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--tblr-border-color);
    color: var(--tblr-secondary);
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.mod-item {
    border-bottom: 1px solid var(--tblr-border-color);
}

.mod-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
    padding: 0.75rem 0;
}

.mod-text {
    min-width: 0;
}

.mod-name {
    margin: 0;
    font-weight: 500;
    color: var(--tblr-body-color);
}

.mod-description {
    max-width: 65ch;
    margin: 0.125rem 0 0;
    color: var(--tblr-secondary);
    font-size: 0.875rem;
    line-height: 1.5;
}

.mod-settings {
    margin: 0 0 0.75rem 0.75rem;
    padding-left: 1.25rem;
    border-left: 1px solid var(--tblr-border-color);
}

.mod-settings > :deep([data-test='mod-setting']:last-child) {
    border-bottom: 0;
}
</style>
