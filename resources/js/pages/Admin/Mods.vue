<script setup lang="ts">
import {Head, useForm} from '@inertiajs/vue3';
import EmptyState from '@/components/EmptyState.vue';
import ModSettingField from '@/components/ModSettingField.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {adminModsUrl, buildSettings, initialSettingValue} from '@/lib/mods';
import type {ModSettingValue} from '@/lib/mods';
import type {AdminMod} from '@/types';

const props = defineProps<{mods: AdminMod[]}>();

const form = useForm({
    mods: Object.fromEntries(
        props.mods.map((mod) => [
            mod.code,
            {
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
            props.mods.map((mod) => [mod.code, {settings: buildSettings(mod.settings, data.mods[mod.code].settings)}]),
        ),
    })).put(adminModsUrl(), {
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
    <AppLayout title="Mod settings">
        <template #breadcrumbs>
            <li class="breadcrumb-item"><a :href="'/'">Home</a></li>
            <li class="breadcrumb-item active"><a :href="adminModsUrl()">Mods</a></li>
        </template>
        <Head title="Mod settings" />
        <div class="col-xl-8 offset-xl-2 col-lg-10 offset-lg-1">
            <div class="page-header mt-0">
                <h1>Mod settings</h1>
            </div>
            <EmptyState
                v-if="mods.length === 0"
                title="No mods available"
                description="There are no mods installed on this instance yet."
            />
            <form v-else @submit.prevent="submit">
                <div v-for="mod in mods" :key="mod.code" class="card mb-3" data-test="mod" :data-mod="mod.code">
                    <div class="card-header fw-bold">{{ mod.name }}</div>
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
