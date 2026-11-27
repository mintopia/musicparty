<script setup lang="ts">
import {computed} from 'vue';
import type {InertiaForm} from '@inertiajs/vue3';
import SettingRow from '@/components/SettingRow.vue';
import {hintFor, labelFor} from '@/lib/adminSettings';
import type {SettingField} from '@/lib/adminSettings';

const props = defineProps<{
    form: InertiaForm<Record<string, string | boolean>>;
    settings: SettingField[];
}>();

const brandingCodes = new Set(['logo-light', 'logo-dark', 'cover-image', 'favicon']);
const defaultCodes = ['defaultparty', 'timezone'];
const known = new Set([...brandingCodes, ...defaultCodes]);

const siteSettings = computed(() =>
    props.settings.filter((setting) => setting.type === 'string' && !known.has(setting.code)),
);
const providerToggles = computed(() => props.settings.filter((setting) => setting.type === 'boolean'));
const defaultSettings = computed(() =>
    defaultCodes.flatMap((code) => props.settings.filter((setting) => setting.code === code)),
);
const hasSettings = computed(() => props.settings.length > 0);

const timezones = computed(() => {
    const names: string[] = typeof Intl.supportedValuesOf === 'function' ? Intl.supportedValuesOf('timeZone') : [];
    const current = String(props.form.timezone ?? '');
    return current && !names.includes(current) ? [current, ...names] : names;
});
</script>

<template>
    <div id="panel-general" role="tabpanel" aria-labelledby="tab-general">
        <p v-if="!hasSettings" class="p-4 mb-0 text-secondary">There are no settings to change.</p>
        <section v-if="siteSettings.length > 0" class="mp-sec">
            <h2>Site</h2>
            <SettingRow
                v-for="setting in siteSettings"
                :key="setting.code"
                :label="labelFor(setting)"
                :for="setting.code"
                :hint="hintFor(setting)"
                :error="form.errors[setting.code]"
            >
                <input
                    :id="setting.code"
                    v-model="form[setting.code]"
                    type="text"
                    :name="setting.code"
                    class="form-control"
                    :class="{'is-invalid': form.errors[setting.code]}"
                />
            </SettingRow>
        </section>
        <section v-if="defaultSettings.length > 0" class="mp-sec">
            <h2>Defaults</h2>
            <SettingRow
                v-for="setting in defaultSettings"
                :key="setting.code"
                :label="labelFor(setting)"
                :for="setting.code"
                :hint="hintFor(setting)"
                :error="form.errors[setting.code]"
            >
                <input
                    v-if="setting.code === 'defaultparty'"
                    :id="setting.code"
                    v-model="form[setting.code]"
                    type="text"
                    :name="setting.code"
                    class="mp-code-input"
                    :class="{'is-invalid': form.errors[setting.code]}"
                    maxlength="4"
                    autocapitalize="characters"
                    autocomplete="off"
                    spellcheck="false"
                    placeholder="KXQB"
                />
                <select
                    v-else
                    :id="setting.code"
                    v-model="form[setting.code]"
                    :name="setting.code"
                    class="form-select"
                    :class="{'is-invalid': form.errors[setting.code]}"
                >
                    <option value="">Use the application timezone</option>
                    <option v-for="zone in timezones" :key="zone" :value="zone">{{ zone }}</option>
                </select>
            </SettingRow>
        </section>
        <section v-if="providerToggles.length > 0" class="mp-sec">
            <h2>Music providers</h2>
            <SettingRow
                v-for="setting in providerToggles"
                :key="setting.code"
                :label="labelFor(setting)"
                :for="setting.code"
                :hint="hintFor(setting)"
            >
                <span class="form-check form-switch mb-0 ps-0 min-height-0">
                    <input
                        :id="setting.code"
                        v-model="form[setting.code]"
                        type="checkbox"
                        role="switch"
                        class="form-check-input m-0"
                        :name="setting.code"
                    />
                </span>
            </SettingRow>
        </section>
    </div>
</template>
