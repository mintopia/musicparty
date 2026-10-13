<script setup lang="ts">
import {Head, useForm} from '@inertiajs/vue3';
import ModSettingField from '@/components/ModSettingField.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {buildSettings, initialSettingValue, partyModsUrl} from '@/lib/mods';
import type {ModSettingValue} from '@/lib/mods';
import type {PartyMod} from '@/types';

const props = defineProps<{
    party: {code: string; name: string};
    mods: PartyMod[];
}>();

const form = useForm({
    mods: Object.fromEntries(
        props.mods.map((mod) => [
            mod.code,
            {
                enabled: mod.enabled,
                settings: Object.fromEntries(mod.settings.map((s) => [s.code, initialSettingValue(s)])) as Record<
                    string,
                    ModSettingValue
                >,
            },
        ]),
    ),
});

function errorFor(modCode: string, settingCode: string): string | undefined {
    return (form.errors as Record<string, string | undefined>)[`mods.${modCode}.settings.${settingCode}`];
}

function submit(): void {
    form.transform((data) => ({
        mods: Object.fromEntries(
            props.mods.map((mod) => [
                mod.code,
                {
                    enabled: data.mods[mod.code].enabled,
                    settings: buildSettings(mod.settings, data.mods[mod.code].settings),
                },
            ]),
        ),
    })).put(partyModsUrl(props.party.code), {
        preserveScroll: true,
        onSuccess: () => {
            for (const mod of props.mods) {
                for (const s of mod.settings) {
                    if (s.type === 'secret') {
                        form.mods[mod.code].settings[s.code] = '';
                    }
                }
            }
        },
    });
}
</script>

<template>
    <AppLayout :title="`Mods - ${party.name}`">
        <template #breadcrumbs>
            <li class="breadcrumb-item"><a :href="'/'">Home</a></li>
            <li class="breadcrumb-item">
                <a :href="`/parties/${party.code}`">{{ party.name }}</a>
            </li>
            <li class="breadcrumb-item active">
                <a :href="partyModsUrl(party.code)">Mods</a>
            </li>
        </template>
        <Head :title="`Mods - ${party.name}`" />
        <div class="col-xl-8 offset-xl-2 col-lg-10 offset-lg-1">
            <div class="page-header mt-0">
                <h1>Mods</h1>
            </div>
            <form @submit.prevent="submit">
                <div v-for="mod in mods" :key="mod.code" class="card mb-3" data-test="mod" :data-mod="mod.code">
                    <div class="card-header">
                        <div class="form-check form-switch mb-0">
                            <input
                                :id="`mod-${mod.code}-enabled`"
                                v-model="form.mods[mod.code].enabled"
                                type="checkbox"
                                class="form-check-input"
                                data-test="mod-enabled"
                            />
                            <label :for="`mod-${mod.code}-enabled`" class="form-check-label fw-bold">
                                {{ mod.name }}
                            </label>
                        </div>
                    </div>
                    <div class="card-body">
                        <p v-if="mod.description" class="text-secondary">{{ mod.description }}</p>
                        <ModSettingField
                            v-for="setting in mod.settings"
                            :id="`mod-${mod.code}-${setting.code}`"
                            :key="setting.code"
                            v-model="form.mods[mod.code].settings[setting.code]"
                            :setting="setting"
                            :error="errorFor(mod.code, setting.code)"
                        />
                    </div>
                </div>
                <button type="submit" class="btn btn-primary" :disabled="form.processing" data-test="save">Save</button>
            </form>
        </div>
    </AppLayout>
</template>
