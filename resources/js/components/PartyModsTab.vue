<script setup lang="ts">
import {computed} from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import AiReviewSettings from '@/components/AiReviewSettings.vue';
import ModCard from '@/components/ModCard.vue';
import ModSettingField from '@/components/ModSettingField.vue';
import {AI_REVIEW_MOD, aiReviewStats, groupMods, hasSavedApiKey, modIcon} from '@/lib/mods';
import type {ModDraft, ModStat, ModStatus} from '@/lib/mods';
import type {PartyMod} from '@/types';

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

function settingErrors(modCode: string): Record<string, string | undefined> {
    const prefix = `mods.${modCode}.settings.`;
    return Object.fromEntries(
        Object.entries(props.errors[modCode] ?? {})
            .filter(([key]) => key.startsWith(prefix))
            .map(([key, message]) => [key.slice(prefix.length), message]),
    );
}

function statsFor(mod: PartyMod): ModStat[] | undefined {
    return mod.code === AI_REVIEW_MOD ? aiReviewStats(mod.settings, props.drafts[mod.code]!.settings) : undefined;
}

function statusFor(mod: PartyMod): ModStatus | undefined {
    const needsKey = mod.code === AI_REVIEW_MOD && !hasSavedApiKey(mod.settings) && mod.settings.length > 0;
    return props.drafts[mod.code]!.enabled && needsKey ? {label: 'Needs an API key', tone: 'warning'} : undefined;
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
            <h2 class="mod-group-heading">
                {{ group.name }} <span class="mod-group-count">{{ group.mods.length }}</span>
            </h2>
            <ModCard
                v-for="mod in group.mods"
                :id="`mod-${mod.code}`"
                :key="mod.code"
                v-model:enabled="drafts[mod.code]!.enabled"
                :name="mod.name"
                :description="mod.description"
                :icon="modIcon(mod.code)"
                :stats="statsFor(mod)"
                :status="statusFor(mod)"
            >
                <div class="mod-settings" :data-test="`mod-settings-${mod.code}`">
                    <div
                        v-if="mod.code === AI_REVIEW_MOD && !holdsFlagged"
                        class="alert alert-warning py-2 m-3"
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
                        class="text-danger small px-3"
                        data-test="mod-error"
                    >
                        {{ message }}
                    </div>
                    <AiReviewSettings
                        v-if="mod.code === AI_REVIEW_MOD"
                        v-model="drafts[mod.code]!.settings"
                        :id="`mod-${mod.code}`"
                        :settings="mod.settings"
                        :errors="settingErrors(mod.code)"
                    />
                    <div v-else class="mod-fields">
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
                </div>
            </ModCard>
            <template v-for="mod in group.mods" :key="`off-${mod.code}`">
                <div
                    v-if="!drafts[mod.code]!.enabled && errors[mod.code]"
                    class="text-danger small mt-1"
                    data-test="mod-error"
                >
                    {{ Object.values(errors[mod.code]!)[0] }}
                </div>
            </template>
        </section>
    </div>
</template>

<style scoped>
.mod-group + .mod-group {
    margin-top: 2rem;
}

.mod-group-heading {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0;
    font-size: 1.125rem;
    font-weight: 700;
}

.mod-group-count {
    padding: 0 0.5rem;
    border-radius: 999px;
    background: var(--tblr-bg-surface-secondary);
    color: var(--tblr-secondary);
    font-size: 0.75rem;
    font-weight: 600;
}

.mod-fields {
    padding: 0 1.25rem;
}

.mod-fields > :deep([data-test='mod-setting']:last-child) {
    border-bottom: 0;
}
</style>
