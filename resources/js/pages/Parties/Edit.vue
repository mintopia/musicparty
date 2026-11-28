<script setup lang="ts">
import {Head, router, useForm} from '@inertiajs/vue3';
import {computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch} from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import PartyModsTab from '@/components/PartyModsTab.vue';
import PartyPlayer from '@/components/PartyPlayer.vue';
import PartySettingsFields from '@/components/PartySettingsFields.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {partyCrumbs} from '@/lib/breadcrumbs';
import {buildSettings, cloneDraft, initialModDraft, isModDirty, partyModUrl, partyModUsersUrl} from '@/lib/mods';
import type {ModDraft} from '@/lib/mods';
import {initialPartyForm} from '@/lib/partyForm';
import type {NamedOption, PartyFormValues, PlaylistOption} from '@/lib/partyForm';
import {update} from '@/routes/parties';
import type {LegacyPartyState, PartyMod} from '@/types';

const TABS = [
    {id: 'general', label: 'General'},
    {id: 'requests', label: 'Requests'},
    {id: 'playback', label: 'Playback'},
    {id: 'screens', label: 'Screens'},
    {id: 'mods', label: 'Mods'},
] as const;

type TabId = (typeof TABS)[number]['id'];

const FIELD_TAB: Record<string, TabId> = {
    active: 'general',
    name: 'general',
    country: 'general',
    timezone: 'general',
    allow_requests: 'requests',
    downvotes: 'requests',
    weighted: 'requests',
    explicit: 'requests',
    flag_requests: 'requests',
    max_requests: 'requests',
    downvotes_per_hour: 'requests',
    min_song_length: 'requests',
    max_song_length: 'requests',
    no_repeat_interval: 'requests',
    backup_playlist_id: 'playback',
    custom_backup_playlist_id: 'playback',
    history_playlist_id: 'playback',
    device_id: 'playback',
    force: 'playback',
    poll: 'playback',
    context_playlist_id: 'playback',
    show_qrcode: 'screens',
};

const ANCHOR_TAB: Record<string, TabId> = {
    flag_requests: 'requests',
    'encore-relay': 'screens',
};

const props = defineProps<{
    party: PartyFormValues & {code: string};
    providerName: string;
    canEditCode?: boolean;
    playlists: PlaylistOption[];
    hostName?: string | null;
    devices: NamedOption[];
    defaultCountry: string;
    instanceTimezone: string;
    timezones: string[];
    playerState: LegacyPartyState;
    contextPlaylistName?: string | null;
    encore: {host: string; key: string | null; channel: string; secret: string | null};
    mods?: PartyMod[];
    tab?: string;
}>();

const isTab = (value: string | null | undefined): value is TabId => TABS.some((t) => t.id === value);

const initialValues = {
    ...initialPartyForm(props.party, props.playlists, props.defaultCountry),
    ...(props.canEditCode ? {code: props.party.code} : {}),
};
const form = useForm(initialValues);
const baseline = reactive<Record<string, unknown>>({...initialValues});

const modList = computed(() => props.mods ?? []);
const drafts = reactive<Record<string, ModDraft>>({});
const modBaselines = reactive<Record<string, ModDraft>>({});
for (const mod of modList.value) {
    drafts[mod.code] = initialModDraft(mod);
    modBaselines[mod.code] = cloneDraft(drafts[mod.code]!);
}
const modErrors = reactive<Record<string, Record<string, string>>>({});

const activeTab = ref<TabId>(isTab(props.tab) ? props.tab : 'general');
const saving = ref(false);

function selectTab(id: TabId): void {
    activeTab.value = id;
}

watch(activeTab, (id) => {
    const url = new URL(window.location.href);
    url.searchParams.set('tab', id);
    url.hash = '';
    window.history.replaceState(window.history.state, '', url);
});

function onTabKeydown(event: KeyboardEvent): void {
    const step = event.key === 'ArrowRight' ? 1 : event.key === 'ArrowLeft' ? -1 : 0;
    if (step === 0) {
        return;
    }
    event.preventDefault();
    const index = TABS.findIndex((t) => t.id === activeTab.value);
    const next = TABS[(index + step + TABS.length) % TABS.length]!;
    selectTab(next.id);
    void nextTick(() => document.getElementById(`tab-${next.id}`)?.focus());
}

async function applyHash(): Promise<void> {
    const anchor = window.location.hash.slice(1);
    if (anchor === '') {
        return;
    }
    const target = ANCHOR_TAB[anchor] ?? (anchor.startsWith('mod-') ? 'mods' : null);
    if (target === null) {
        return;
    }
    activeTab.value = target;
    await nextTick();
    document.getElementById(anchor)?.scrollIntoView?.({block: 'center'});
}

onMounted(() => {
    void applyHash();
    window.addEventListener('hashchange', applyHash);
});
onBeforeUnmount(() => window.removeEventListener('hashchange', applyHash));

const changedFields = computed(() =>
    Object.keys(baseline).filter((key) => (form as unknown as Record<string, unknown>)[key] !== baseline[key]),
);
const changedMods = computed(() =>
    modList.value.filter((mod) => drafts[mod.code] && isModDirty(drafts[mod.code]!, modBaselines[mod.code]!)),
);
const changeCount = computed(() => changedFields.value.length + changedMods.value.length);
const dirty = computed(() => changeCount.value > 0);

const changeSummary = computed(() => {
    const labels = new Set<string>();
    for (const field of changedFields.value) {
        const tab = FIELD_TAB[field];
        labels.add(TABS.find((t) => t.id === tab)?.label ?? 'Settings');
    }
    for (const mod of changedMods.value) {
        labels.add(mod.name);
    }
    const count = `${changeCount.value} ${changeCount.value === 1 ? 'change' : 'changes'}`;
    return labels.size > 0 ? `${count}: ${[...labels].join(', ')}` : count;
});

function discard(): void {
    for (const key of Object.keys(baseline)) {
        (form as unknown as Record<string, unknown>)[key] = baseline[key];
    }
    form.clearErrors?.();
    for (const mod of modList.value) {
        drafts[mod.code] = cloneDraft(modBaselines[mod.code]!);
        delete modErrors[mod.code];
    }
}

function patchParty(): Promise<boolean> {
    return new Promise((resolve) => {
        form.patch(update(props.party.code).url, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                for (const key of Object.keys(baseline)) {
                    baseline[key] = (form as unknown as Record<string, unknown>)[key];
                }
                resolve(true);
            },
            onError: () => resolve(false),
        });
    });
}

function saveMod(mod: PartyMod): Promise<boolean> {
    const draft = drafts[mod.code]!;
    return new Promise((resolve) => {
        router.put(
            partyModUrl(props.party.code, mod.code),
            {enabled: draft.enabled, settings: buildSettings(mod.settings, draft.settings)},
            {
                preserveScroll: true,
                preserveState: true,
                only: ['mods'],
                onSuccess: () => {
                    for (const s of mod.settings) {
                        if (s.type === 'secret') {
                            draft.settings[s.code] = '';
                        }
                    }
                    modBaselines[mod.code] = cloneDraft(draft);
                    delete modErrors[mod.code];
                    resolve(true);
                },
                onError: (errors: Record<string, string>) => {
                    const own = Object.fromEntries(
                        Object.entries(errors).filter(([key]) => key.startsWith(`mods.${mod.code}`)),
                    );
                    modErrors[mod.code] = Object.keys(own).length > 0 ? own : errors;
                    resolve(false);
                },
            },
        );
    });
}

async function save(): Promise<void> {
    if (saving.value || !dirty.value) {
        return;
    }
    saving.value = true;
    try {
        const queue = [...changedMods.value];
        if (changedFields.value.length > 0 && !(await patchParty())) {
            const first = Object.keys(form.errors)[0];
            const tab = first ? FIELD_TAB[first] : undefined;
            if (tab && queue.length === 0) {
                selectTab(tab);
            }
        }
        for (const mod of queue) {
            await saveMod(mod);
        }
        const failed = Object.keys(modErrors).find((code) => modList.value.some((m) => m.code === code));
        if (failed) {
            selectTab('mods');
        }
    } finally {
        saving.value = false;
    }
}

function holdFlagged(): void {
    form.flag_requests = true;
}
</script>

<template>
    <AppLayout title="Settings">
        <template #breadcrumbs>
            <Breadcrumbs
                :items="[...partyCrumbs({code: party.code, name: party.name ?? party.code}), {label: 'Settings'}]"
            />
        </template>
        <template #header>
            <PartyPlayer :code="party.code" :initial-state="playerState" />
        </template>
        <Head title="Settings" />
        <div class="col-xl-8 offset-xl-2 col-lg-10 offset-lg-1">
            <div class="page-header mt-0">
                <h1>Settings</h1>
                <p class="settings-lede">How {{ party.name ?? party.code }} runs. Changes apply when you save.</p>
            </div>

            <form class="settings-form mp-touch" data-test="settings-form" @submit.prevent="save">
                <div class="underline-tabs" role="tablist" aria-label="Settings sections" @keydown="onTabKeydown">
                    <button
                        v-for="t in TABS"
                        :id="`tab-${t.id}`"
                        :key="t.id"
                        type="button"
                        role="tab"
                        class="underline-tab"
                        :class="{active: activeTab === t.id}"
                        :aria-selected="activeTab === t.id"
                        :aria-controls="`panel-${t.id}`"
                        :tabindex="activeTab === t.id ? 0 : -1"
                        :data-test="`tab-${t.id}`"
                        @click="selectTab(t.id)"
                    >
                        {{ t.label }}
                    </button>
                </div>

                <div
                    :id="`panel-${activeTab}`"
                    class="settings-panel"
                    role="tabpanel"
                    :aria-labelledby="`tab-${activeTab}`"
                >
                    <PartyModsTab
                        v-if="activeTab === 'mods'"
                        :mods="modList"
                        :drafts="drafts"
                        :errors="modErrors"
                        :user-search-url="partyModUsersUrl(party.code)"
                        :holds-flagged="form.flag_requests"
                        @hold-flagged="holdFlagged"
                    />
                    <PartySettingsFields
                        v-else
                        :tab="activeTab"
                        :form="form"
                        :can-edit-code="canEditCode"
                        :playlists="playlists"
                        :host-name="hostName ?? undefined"
                        :devices="devices"
                        :provider-name="providerName"
                        :instance-timezone="instanceTimezone"
                        :timezones="timezones"
                        :context-playlist-name="contextPlaylistName"
                        :encore="encore"
                    />
                </div>

                <div v-if="dirty" class="save-bar" role="region" aria-label="Unsaved changes" data-test="save-bar">
                    <span class="save-summary" aria-live="polite" data-test="save-summary">{{ changeSummary }}</span>
                    <button type="button" class="btn btn-link" :disabled="saving" data-test="discard" @click="discard">
                        Discard
                    </button>
                    <button type="submit" class="btn btn-primary" :disabled="saving" data-test="save">
                        Save changes
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<style scoped>
.settings-panel {
    padding: 1.5rem 0 2rem;
}

.save-bar {
    position: sticky;
    bottom: 0;
    z-index: 5;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.75rem;
    padding: 0.75rem 0;
    border-top: 1px solid var(--tblr-border-color);
    background: var(--tblr-bg-surface, var(--tblr-body-bg));
}

.settings-lede {
    margin: 0.5rem 0 0;
    max-inline-size: 64ch;
    color: var(--tblr-secondary-color);
    font-size: 1rem;
}

.save-bar .btn {
    min-block-size: 44px;
}

@media (max-width: 575.98px) {
    .save-bar {
        flex-wrap: wrap;
        gap: 0.5rem 0.75rem;
    }

    .save-summary {
        flex: 1 0 100%;
    }

    .save-bar .btn-primary {
        flex: 1;
    }
}

.save-summary {
    margin-right: auto;
    color: var(--tblr-secondary);
    font-size: 0.875rem;
}
</style>
