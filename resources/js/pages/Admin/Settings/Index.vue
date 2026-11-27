<script setup lang="ts">
import {Head, Link, useForm} from '@inertiajs/vue3';
import {computed, ref} from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import CopyButton from '@/components/CopyButton.vue';
import PageHead from '@/components/PageHead.vue';
import SettingRow from '@/components/SettingRow.vue';
import StatePill from '@/components/StatePill.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {adminCrumbs} from '@/lib/breadcrumbs';
import {index as adminThemes} from '@/routes/admin/themes';

interface SettingField {
    code: string;
    name: string;
    description: string | null;
    type: 'string' | 'boolean';
    required: boolean;
    value: string | boolean;
}

interface SocialProviderRow {
    id: number;
    code: string;
    name: string;
    supports_auth: boolean;
    enabled: boolean;
    auth_enabled: boolean;
    needs_secret: boolean;
    login_return_url: string;
    linked_account_url: string;
}

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

const copy: Record<string, {label: string; hint: string}> = {
    name: {label: 'Site name', hint: 'Shown in the sidebar, the browser tab and emails.'},
    terms: {label: 'Terms and Conditions URL', hint: 'Guests must agree to this on first login. Leave empty to skip.'},
    privacypolicy: {label: 'Privacy Policy URL', hint: 'Linked from the login page.'},
    defaultparty: {
        label: 'Default party code',
        hint: 'Four letters, no digits. Anyone who opens the site goes straight to this party.',
    },
    timezone: {
        label: 'Timezone',
        hint: 'Used by time-based Mods and Scheduled Requests. Parties can override it.',
    },
    'logo-light': {
        label: 'Logo for dark backgrounds',
        hint: 'A light version, used in the sidebar and on the login page.',
    },
    'logo-dark': {label: 'Logo for light backgrounds', hint: 'A dark version, used in emails and on light pages.'},
    'cover-image': {label: 'Login cover image', hint: 'The large photo beside the Discord button.'},
    favicon: {label: 'Favicon', hint: 'Shown in the browser tab. Square, at least 64 pixels.'},
    'provider-spotify-enabled': {label: 'Spotify', hint: 'Lets parties use Spotify as their Music Provider.'},
    'provider-fake-enabled': {
        label: 'Fake Music Provider',
        hint: 'For local development only. Turn off on a real instance.',
    },
};

const logoCodes = ['logo-light', 'logo-dark'];
const browserCodes = ['cover-image', 'favicon'];
const defaultCodes = ['defaultparty', 'timezone'];

const byCode = (codes: string[]): SettingField[] =>
    codes.flatMap((code) => props.settings.filter((setting) => setting.code === code));
const known = new Set([...logoCodes, ...browserCodes, ...defaultCodes]);
const siteSettings = computed(() =>
    props.settings.filter((setting) => setting.type === 'string' && !known.has(setting.code)),
);
const providerToggles = computed(() => props.settings.filter((setting) => setting.type === 'boolean'));
const logoSettings = computed(() => byCode(logoCodes));
const browserSettings = computed(() => byCode(browserCodes));
const defaultSettings = computed(() => byCode(defaultCodes));
const hasSettings = computed(() => props.settings.length > 0);

const form = useForm<Record<string, string | boolean>>(
    Object.fromEntries(props.settings.map((setting) => [setting.code, setting.value])),
);

const changedCount = computed(() => props.settings.filter((setting) => form[setting.code] !== setting.value).length);

const timezones = computed(() => {
    const names: string[] = typeof Intl.supportedValuesOf === 'function' ? Intl.supportedValuesOf('timeZone') : [];
    const current = String(form.timezone ?? '');
    return current && !names.includes(current) ? [current, ...names] : names;
});

const tab = ref<TabId>('general');
const formTabs: TabId[] = ['general', 'branding'];

function labelFor(setting: SettingField): string {
    return copy[setting.code]?.label ?? setting.name;
}

function hintFor(setting: SettingField): string | undefined {
    return copy[setting.code]?.hint ?? setting.description ?? undefined;
}

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

const providerIcons: Record<string, string> = {
    discord: 'ti-brand-discord',
    spotify: 'ti-brand-spotify',
    spotifysearch: 'ti-brand-spotify',
    steam: 'ti-brand-steam',
    twitch: 'ti-brand-twitch',
};

function providerStatus(provider: SocialProviderRow): {
    state: 'ok' | 'live' | 'idle' | 'warn';
    label: string;
    note: string | null;
} {
    if (!provider.enabled) {
        return {state: 'idle', label: 'Off', note: provider.supports_auth ? null : 'Search only, cannot log in'};
    }
    if (provider.needs_secret) {
        return {state: 'warn', label: 'Needs a secret', note: 'On, but the client secret is empty'};
    }
    if (provider.auth_enabled) {
        return {state: 'ok', label: 'Sign-in on', note: null};
    }
    return {
        state: 'live',
        label: 'Linking only',
        note: provider.supports_auth ? 'Sign-in is off' : 'Used to play and search, not to log in',
    };
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
                    <div v-show="tab === 'general'" id="panel-general" role="tabpanel" aria-labelledby="tab-general">
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

                    <div v-show="tab === 'branding'" id="panel-branding" role="tabpanel" aria-labelledby="tab-branding">
                        <section class="mp-sec">
                            <h2>Logos</h2>
                            <SettingRow
                                v-for="setting in logoSettings"
                                :key="setting.code"
                                stacked
                                :label="labelFor(setting)"
                                :for="setting.code"
                                :hint="hintFor(setting)"
                                :error="form.errors[setting.code]"
                            >
                                <input
                                    :id="setting.code"
                                    v-model="form[setting.code]"
                                    type="url"
                                    :name="setting.code"
                                    class="form-control"
                                    :class="{'is-invalid': form.errors[setting.code]}"
                                />
                                <figure
                                    v-if="form[setting.code]"
                                    class="brand-preview m-0"
                                    :data-test="`preview-${setting.code}`"
                                >
                                    <span
                                        class="brand-tile"
                                        :class="setting.code === 'logo-light' ? 'is-sidebar' : 'is-surface'"
                                    >
                                        <img :src="String(form[setting.code])" alt="" />
                                    </span>
                                    <figcaption>
                                        {{
                                            setting.code === 'logo-light'
                                                ? 'Preview on the sidebar colour'
                                                : 'Preview on white'
                                        }}
                                    </figcaption>
                                </figure>
                            </SettingRow>
                        </section>
                        <section class="mp-sec">
                            <h2>Login and browser</h2>
                            <SettingRow
                                v-for="setting in browserSettings"
                                :key="setting.code"
                                stacked
                                :label="labelFor(setting)"
                                :for="setting.code"
                                :hint="hintFor(setting)"
                                :error="form.errors[setting.code]"
                            >
                                <input
                                    :id="setting.code"
                                    v-model="form[setting.code]"
                                    type="url"
                                    :name="setting.code"
                                    class="form-control"
                                    :class="{'is-invalid': form.errors[setting.code]}"
                                />
                                <figure
                                    v-if="form[setting.code]"
                                    class="brand-preview m-0"
                                    :data-test="`preview-${setting.code}`"
                                >
                                    <img
                                        v-if="setting.code === 'cover-image'"
                                        class="brand-cover"
                                        :src="String(form[setting.code])"
                                        alt=""
                                    />
                                    <span v-else class="brand-tile is-favicon">
                                        <img :src="String(form[setting.code])" alt="" />
                                    </span>
                                    <figcaption>
                                        {{
                                            setting.code === 'cover-image'
                                                ? 'Preview, cropped as on a laptop'
                                                : '32 pixels, as in a tab'
                                        }}
                                    </figcaption>
                                </figure>
                            </SettingRow>
                        </section>
                    </div>
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

            <div v-show="tab === 'providers'" id="panel-providers" role="tabpanel" aria-labelledby="tab-providers">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Sign-in providers</h2>
                        <span class="ms-auto text-secondary d-none d-md-inline"
                            >Copy a return URL into the provider's developer console.</span
                        >
                    </div>
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table">
                            <thead>
                                <tr>
                                    <th>Provider</th>
                                    <th>Status</th>
                                    <th>Return URLs</th>
                                    <th class="w-1"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="provider in socialProviders" :key="provider.id">
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <i
                                                class="icon ti text-secondary"
                                                :class="providerIcons[provider.code] ?? 'ti-key'"
                                                aria-hidden="true"
                                            ></i>
                                            <div>
                                                <div class="fw-medium">{{ provider.name }}</div>
                                                <div class="text-secondary small">{{ provider.code }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <StatePill :state="providerStatus(provider).state">{{
                                            providerStatus(provider).label
                                        }}</StatePill>
                                        <div v-if="providerStatus(provider).note" class="text-secondary small mt-1">
                                            {{ providerStatus(provider).note }}
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-2">
                                            <CopyButton
                                                :value="provider.login_return_url"
                                                :label="`Copy ${provider.name} sign-in URL`"
                                                text="Sign-in URL"
                                            />
                                            <CopyButton
                                                :value="provider.linked_account_url"
                                                :label="`Copy ${provider.name} linking URL`"
                                                text="Linking URL"
                                            />
                                        </div>
                                    </td>
                                    <td>
                                        <Link
                                            :href="`/admin/settings/socialproviders/${provider.id}/edit`"
                                            class="btn btn-sm btn-outline-secondary"
                                        >
                                            <i class="icon ti ti-edit" aria-hidden="true"></i>
                                            Edit
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="socialProviders.length === 0">
                                    <td colspan="4" class="text-center p-4">
                                        <p class="mb-0">There are no social providers</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.brand-preview {
    display: grid;
    gap: 0.375rem;
    justify-items: start;
    inline-size: 100%;
}

.brand-preview figcaption {
    color: var(--tblr-secondary-color);
    font-size: var(--mp-fs-small);
}

.brand-tile {
    display: grid;
    place-items: center;
    min-inline-size: 11rem;
    block-size: 4.5rem;
    border: 1px solid var(--tblr-border-color);
    border-radius: var(--tblr-border-radius);
}

.brand-tile img {
    max-block-size: 2.25rem;
    max-inline-size: 80%;
}

.brand-tile.is-sidebar {
    background: var(--mp-nav-background, var(--mp-default-nav-background));
}

.brand-tile.is-surface {
    background: var(--tblr-white);
}

.brand-tile.is-favicon {
    min-inline-size: 2rem;
    inline-size: 2rem;
    block-size: 2rem;
}

.brand-tile.is-favicon img {
    max-block-size: 1.25rem;
}

.brand-cover {
    inline-size: 100%;
    max-inline-size: 12rem;
    aspect-ratio: 16 / 10;
    object-fit: cover;
    border: 1px solid var(--tblr-border-color);
    border-radius: var(--tblr-border-radius);
}
</style>
