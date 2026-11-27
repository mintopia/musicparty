<script setup lang="ts">
import {Head, Link, useForm} from '@inertiajs/vue3';
import {computed, ref} from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import BrandingSettingsPanel from '@/components/AdminSettings/BrandingSettingsPanel.vue';
import GeneralSettingsPanel from '@/components/AdminSettings/GeneralSettingsPanel.vue';
import SocialProvidersPanel from '@/components/AdminSettings/SocialProvidersPanel.vue';
import PageHead from '@/components/PageHead.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {adminCrumbs} from '@/lib/breadcrumbs';
import type {SettingField, SocialProviderRow} from '@/lib/adminSettings';
import {index as adminThemes} from '@/routes/admin/themes';

const props = defineProps<{
    settings: SettingField[];
    socialProviders: SocialProviderRow[];
}>();

const TABS = [
    {id: 'general', label: 'General'},
    {id: 'branding', label: 'Branding'},
    {id: 'providers', label: 'Sign-in providers'},
] as const;

type TabId = (typeof TABS)[number]['id'];

const form = useForm<Record<string, string | boolean>>(
    Object.fromEntries(props.settings.map((setting) => [setting.code, setting.value])),
);

const changedCount = computed(() => props.settings.filter((setting) => form[setting.code] !== setting.value).length);

const tab = ref<TabId>('general');
const formTabs: TabId[] = ['general', 'branding'];

function discard(): void {
    for (const setting of props.settings) {
        form[setting.code] = setting.value;
    }
}

function submit(): void {
    form.patch('/admin/settings');
}

function onTabKeydown(event: KeyboardEvent): void {
    const index = TABS.findIndex((t) => t.id === tab.value);
    const step = event.key === 'ArrowRight' ? 1 : event.key === 'ArrowLeft' ? -1 : 0;
    if (step === 0) {
        return;
    }
    const next = TABS[(index + step + TABS.length) % TABS.length];
    tab.value = next.id;
    document.getElementById(`tab-${next.id}`)?.focus();
}
</script>

<template>
    <AppLayout title="Settings">
        <template #breadcrumbs>
            <Breadcrumbs :items="[...adminCrumbs(), {label: 'Settings'}]" />
        </template>
        <Head title="Settings" />
        <div class="mp-narrow mp-touch">
            <PageHead title="Settings" summary="Site-wide settings for this Music Party instance." />

            <div class="underline-tabs mb-4" role="tablist" aria-label="Settings sections" @keydown="onTabKeydown">
                <button
                    v-for="t in TABS"
                    :id="`tab-${t.id}`"
                    :key="t.id"
                    type="button"
                    role="tab"
                    class="underline-tab"
                    :class="{active: tab === t.id}"
                    :aria-selected="tab === t.id"
                    :aria-controls="`panel-${t.id}`"
                    :tabindex="tab === t.id ? 0 : -1"
                    :data-test="`tab-${t.id}`"
                    @click="tab = t.id"
                >
                    {{ t.label }}
                </button>
                <Link :href="adminThemes().url" class="underline-tab" data-test="themes-link">
                    Themes
                    <i class="icon ti ti-arrow-up-right" aria-hidden="true"></i>
                </Link>
            </div>

            <form v-show="formTabs.includes(tab)" data-test="settings-form" @submit.prevent="submit">
                <div class="card">
                    <GeneralSettingsPanel v-show="tab === 'general'" :form="form" :settings="settings" />
                    <BrandingSettingsPanel v-show="tab === 'branding'" :form="form" :settings="settings" />
                </div>

                <div
                    v-if="changedCount > 0"
                    class="mp-savebar"
                    role="region"
                    aria-label="Unsaved changes"
                    data-test="save-bar"
                >
                    <span class="mp-grow" aria-live="polite">
                        {{ changedCount }} unsaved {{ changedCount === 1 ? 'change' : 'changes' }}
                    </span>
                    <button type="button" class="btn btn-link" :disabled="form.processing" @click="discard">
                        Discard
                    </button>
                    <button type="submit" class="btn btn-primary" :disabled="form.processing">Save changes</button>
                </div>
            </form>

            <SocialProvidersPanel v-show="tab === 'providers'" :social-providers="socialProviders" />
        </div>
    </AppLayout>
</template>
