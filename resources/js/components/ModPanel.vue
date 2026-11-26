<script setup lang="ts">
import {useForm} from '@inertiajs/vue3';
import {ref, watch} from 'vue';
import ModAccordionItem from '@/components/ModAccordionItem.vue';
import ModSettingField from '@/components/ModSettingField.vue';
import {buildSettings, initialSettingValue} from '@/lib/mods';
import type {ModSettingValue} from '@/lib/mods';
import type {AdminMod, PartyMod} from '@/types';

const props = defineProps<{
    mod: PartyMod | AdminMod;
    url: string;
    userSearchUrl: string;
    showEnabled?: boolean;
}>();

const initiallyEnabled = 'enabled' in props.mod ? props.mod.enabled : false;
const open = ref(props.showEnabled ? initiallyEnabled : false);

const form = useForm({
    enabled: initiallyEnabled,
    settings: Object.fromEntries(props.mod.settings.map((s) => [s.code, initialSettingValue(s)])) as Record<
        string,
        ModSettingValue
    >,
});

watch(
    () => form.enabled,
    (enabled) => {
        if (enabled) {
            open.value = true;
        }
    },
);

function errorFor(settingCode: string): string | undefined {
    return (form.errors as Record<string, string | undefined>)[`mods.${props.mod.code}.settings.${settingCode}`];
}

function submit(): void {
    form.transform((data) => ({
        ...(props.showEnabled ? {enabled: data.enabled} : {}),
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
    <ModAccordionItem
        v-model:open="open"
        :id="`mod-${mod.code}`"
        :name="mod.name"
        :description="mod.description"
        :data-mod="mod.code"
    >
        <template v-if="showEnabled" #status>
            <div class="form-check form-switch mb-0">
                <input
                    :id="`mod-${mod.code}-enabled`"
                    v-model="form.enabled"
                    type="checkbox"
                    class="form-check-input"
                    :aria-label="`Enable ${mod.name}`"
                    data-test="mod-enabled"
                />
            </div>
        </template>
        <form data-test="mod-form" @submit.prevent="submit">
            <ModSettingField
                v-for="setting in mod.settings"
                :id="`mod-${mod.code}-${setting.code}`"
                :key="setting.code"
                v-model="form.settings[setting.code]"
                :setting="setting"
                :error="errorFor(setting.code)"
                :user-search-url="userSearchUrl"
            />
            <button type="submit" class="btn btn-primary" :disabled="form.processing" data-test="save">Save</button>
        </form>
    </ModAccordionItem>
</template>
