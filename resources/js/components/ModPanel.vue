<script setup lang="ts">
import {useForm} from '@inertiajs/vue3';
import {ref} from 'vue';
import ModAccordionItem from '@/components/ModAccordionItem.vue';
import ModSettingField from '@/components/ModSettingField.vue';
import {buildSettings, initialSettingValue} from '@/lib/mods';
import type {ModSettingValue} from '@/lib/mods';
import type {AdminMod} from '@/types';

const props = defineProps<{
    mod: AdminMod;
    url: string;
    userSearchUrl: string;
}>();

const open = ref(false);

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
    <ModAccordionItem
        v-model:open="open"
        :id="`mod-${mod.code}`"
        :name="mod.name"
        :description="mod.description"
        :data-mod="mod.code"
    >
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
