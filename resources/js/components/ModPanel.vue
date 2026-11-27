<script setup lang="ts">
import {useForm} from '@inertiajs/vue3';
import {computed, ref} from 'vue';
import ModCard from '@/components/ModCard.vue';
import ModSettingField from '@/components/ModSettingField.vue';
import {AI_REVIEW_MOD, aiReviewStats, buildSettings, initialSettingValue, modIcon} from '@/lib/mods';
import type {ModSettingValue} from '@/lib/mods';
import type {AdminMod} from '@/types';

const props = defineProps<{
    mod: AdminMod;
    url: string;
    userSearchUrl: string;
}>();

const open = ref(false);
const stats = computed(() =>
    props.mod.code === AI_REVIEW_MOD ? aiReviewStats(props.mod.settings, form.settings) : undefined,
);

const form = useForm({
    settings: Object.fromEntries(props.mod.settings.map((s) => [s.code, initialSettingValue(s)])) as Record<
        string,
        ModSettingValue
    >,
});

function errorFor(settingCode: string): string | undefined {
    return (form.errors as Record<string, string | undefined>)[`mods.${props.mod.code}.settings.${settingCode}`];
}

function submit(): void {
    form.transform((data) => ({
        settings: buildSettings(props.mod.settings, data.settings),
    })).put(props.url, {
        preserveScroll: true,
        onSuccess: () => {
            for (const s of props.mod.settings) {
                if (s.type === 'secret') {
                    form.settings[s.code] = '';
                }
            }
        },
    });
}
</script>

<template>
    <ModCard
        v-model:open="open"
        :id="`mod-${mod.code}`"
        :name="mod.name"
        :description="mod.description"
        :icon="modIcon(mod.code)"
        :stats="stats"
        :data-mod="mod.code"
    >
        <form class="mod-panel-form" data-test="mod-form" @submit.prevent="submit">
            <ModSettingField
                v-for="setting in mod.settings"
                :id="`mod-${mod.code}-${setting.code}`"
                :key="setting.code"
                v-model="form.settings[setting.code]"
                row
                :setting="setting"
                :error="errorFor(setting.code)"
                :user-search-url="userSearchUrl"
            />
            <div class="mod-panel-actions">
                <button type="submit" class="btn btn-primary" :disabled="form.processing" data-test="save">Save</button>
            </div>
        </form>
    </ModCard>
</template>

<style scoped>
.mod-panel-form {
    padding: 0.25rem 1.25rem 1rem;
}

.mod-panel-actions {
    display: flex;
    justify-content: flex-end;
    padding-top: 0.75rem;
}

@media (max-width: 767.98px) {
    .mod-panel-actions .btn {
        min-block-size: 44px;
    }
}
</style>
