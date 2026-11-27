<script setup lang="ts">
import {computed} from 'vue';
import ModSettingField from '@/components/ModSettingField.vue';
import {modLayout} from '@/lib/mods';
import type {ModSection} from '@/lib/mods';
import type {ModSettingValue} from '@/lib/mods';
import type {ModSetting} from '@/types';

const props = defineProps<{
    modCode: string;
    id: string;
    settings: ModSetting[];
    errors: Record<string, string | undefined>;
    userSearchUrl: string;
}>();

const values = defineModel<Record<string, ModSettingValue>>({required: true});

interface ResolvedSection extends ModSection {
    fields: ModSetting[];
}

function resolve(section: ModSection): ResolvedSection {
    return {
        ...section,
        fields: section.codes
            .map((code) => props.settings.find((s) => s.code === code))
            .filter((s): s is ModSetting => s !== undefined),
    };
}

const layout = computed(() => modLayout(props.modCode));
const claimed = computed(() => [
    ...(layout.value?.sections.flatMap((s) => s.codes) ?? []),
    ...(layout.value?.advanced?.codes ?? []),
]);
const sections = computed<ResolvedSection[]>(() => {
    const known = (layout.value?.sections ?? []).map(resolve);
    const rest = props.settings.filter((s) => !claimed.value.includes(s.code));
    return rest.length > 0 ? [...known, {title: 'Settings', intro: '', codes: [], fields: rest}] : known;
});
const advanced = computed(() => (layout.value?.advanced ? resolve(layout.value.advanced) : undefined));
const advancedSummary = computed(() => advanced.value?.fields.map((f) => f.name).join(', ') ?? '');
const advancedHasError = computed(() => advanced.value?.fields.some((f) => props.errors[f.code]) ?? false);
</script>

<template>
    <div class="mod-sections" data-test="mod-sections">
        <section
            v-for="section in sections.filter((s) => s.fields.length > 0)"
            :key="section.title"
            class="mod-section"
            :data-section="section.title"
        >
            <div class="mod-section-intro">
                <h4>{{ section.title }}</h4>
                <p v-if="section.intro">{{ section.intro }}</p>
            </div>
            <div class="mod-section-body">
                <ModSettingField
                    v-for="setting in section.fields"
                    :id="`${id}-${setting.code}`"
                    :key="setting.code"
                    v-model="values[setting.code]!"
                    :setting="setting"
                    :error="errors[setting.code]"
                    :user-search-url="userSearchUrl"
                />
            </div>
        </section>
        <details v-if="advanced && advanced.fields.length > 0" class="mod-advanced" :open="advancedHasError">
            <summary>
                <i class="ti ti-chevron-down" aria-hidden="true"></i>Advanced
                <small>{{ advancedSummary }}</small>
            </summary>
            <section class="mod-section" :data-section="advanced.title">
                <div class="mod-section-intro">
                    <h4>{{ advanced.title }}</h4>
                    <p>{{ advanced.intro }}</p>
                </div>
                <div class="mod-section-body mod-section-grid">
                    <ModSettingField
                        v-for="setting in advanced.fields"
                        :id="`${id}-${setting.code}`"
                        :key="setting.code"
                        v-model="values[setting.code]!"
                        :setting="setting"
                        :error="errors[setting.code]"
                        :user-search-url="userSearchUrl"
                    />
                </div>
            </section>
        </details>
    </div>
</template>

<style scoped>
.mod-section {
    display: grid;
    grid-template-columns: minmax(0, 14.5rem) minmax(0, 1fr);
    gap: 0.75rem 2.5rem;
    padding: 1.375rem 1.25rem;
    border-top: 1px solid var(--tblr-border-color);
}

.mod-section-intro h4 {
    margin: 0;
    font-size: 1rem;
    font-weight: 650;
}

.mod-section-intro p {
    margin: 0.25rem 0 0;
    color: var(--tblr-secondary);
    font-size: 0.8125rem;
    line-height: 1.5;
}

.mod-section-body {
    min-width: 0;
}

.mod-section-body > :deep([data-test='mod-setting']:last-child) {
    margin-bottom: 0 !important;
}

.mod-section-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1.125rem;
}

.mod-section-grid > :deep([data-test='mod-setting']) {
    margin-bottom: 0 !important;
}

.mod-advanced {
    border-top: 1px solid var(--tblr-border-color);
}

.mod-advanced > summary {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-block-size: 48px;
    padding: 0.875rem 1.25rem;
    cursor: pointer;
    list-style: none;
    font-weight: 600;
}

.mod-advanced > summary::-webkit-details-marker {
    display: none;
}

.mod-advanced > summary small {
    color: var(--tblr-secondary);
    font-weight: 400;
}

.mod-advanced > summary .ti {
    transition: transform 0.15s;
}

.mod-advanced[open] > summary .ti {
    transform: rotate(180deg);
}

.mod-advanced .mod-section {
    padding-block-start: 0.25rem;
    border-top: 0;
}

@media (max-width: 767.98px) {
    .mod-section,
    .mod-section-grid {
        grid-template-columns: minmax(0, 1fr);
    }
}
</style>
