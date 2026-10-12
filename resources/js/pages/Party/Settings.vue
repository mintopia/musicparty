<script setup>
import {usePartyPresence} from '../../composables/usePartyPresence';
import {Head, Link, useForm, usePage} from '@inertiajs/vue3';
import {computed, provide} from 'vue';
import ModSlot from '../../Components/ModSlot.vue';
import PartyPlayerSettings from '../../Components/PartyPlayerSettings.vue';
import Card from '../../Components/ui/Card.vue';

const props = defineProps({
    party: {type: Object, required: true},
    settings: {type: Object, required: true},
    enabled_mods: {type: Array, default: () => []},
    mods_url: {type: String, default: null},
    theme_url: {type: String, default: null},
    spotify_link_url: {type: String, default: null},
    playlists: {type: Array, default: () => []},
    fallback_playlist_id: {type: String, default: null},
    history_playlist_id: {type: String, default: null},
    fallback_check: {type: Object, default: null},
    player_kind: {type: String, default: null},
    player_kinds: {type: Array, default: () => []},
    playback_device: {type: Object, default: null},
    player_tokens_url: {type: String, default: null},
    devices_url: {type: String, default: null},
    player_update_url: {type: String, default: null},
});

usePartyPresence(props.party.code);

provide('enabledMods', computed(() => props.enabled_mods));

const page = usePage();

const generalForm = useForm({
    name: props.party.name,
});

const saveGeneral = () => {
    generalForm.patch(`/parties/${props.party.code}`, {preserveScroll: true});
};

const displayForm = useForm({
    show_qrcode: props.settings.show_qrcode ?? true,
});

const hasQrCodeSetting = props.settings.show_qrcode !== undefined;

const saveDisplay = () => {
    displayForm.patch(`/parties/${props.party.code}`, {preserveScroll: true});
};

const form = useForm({
    allow_requests: props.settings.allow_requests,
    max_requests: props.settings.max_requests,
    min_song_length: props.settings.min_song_length,
    max_song_length: props.settings.max_song_length,
    explicit: props.settings.explicit,
    hold_requests: props.settings.hold_requests,
    no_repeat_interval: props.settings.no_repeat_interval,
    downvotes: props.settings.downvotes,
    downvotes_per_hour: props.settings.downvotes_per_hour,
    selection_mode: props.settings.selection_mode,
    freeze_when_paused: props.settings.freeze_when_paused,
});

const playlistForm = useForm({
    fallback_playlist: props.fallback_playlist_id ?? '',
    history_playlist: props.history_playlist_id ?? '',
});

const savePlaylists = () => {
    playlistForm.put(`/parties/${props.party.code}/playlists`, {preserveScroll: true});
};

const playlistFields = [
    {key: 'fallback_playlist', label: 'Fallback Playlist', hint: 'Played when the queue is empty. Needs at least 20 playable tracks.', none: 'None'},
    {key: 'history_playlist', label: 'History Playlist', hint: 'Every played track is appended here. Choose none to stop appending.', none: 'None (do not append)'},
];

const toNumberOrNull = (value) => (value === '' || value === null ? null : Number(value));

const save = () => {
    form
        .transform((data) => ({
            ...data,
            max_requests: toNumberOrNull(data.max_requests),
            min_song_length: toNumberOrNull(data.min_song_length),
            max_song_length: toNumberOrNull(data.max_song_length),
            no_repeat_interval: toNumberOrNull(data.no_repeat_interval),
            downvotes_per_hour: toNumberOrNull(data.downvotes_per_hour),
        }))
        .patch(`/parties/${props.party.code}`, {preserveScroll: true});
};

const numberFields = [
    {key: 'max_requests', label: 'Maximum requests per member', hint: 'Leave empty for no limit.'},
    {key: 'min_song_length', label: 'Minimum song length (seconds)', hint: 'Leave empty for no minimum.'},
    {key: 'max_song_length', label: 'Maximum song length (seconds)', hint: 'Leave empty for no maximum.'},
    {key: 'no_repeat_interval', label: 'No-repeat interval (seconds)', hint: 'Leave empty to allow immediate repeats.'},
];
</script>

<template>
    <Head :title="`Settings - ${party.name}`" />
    <div class="flex flex-col gap-4 px-4 pb-4 pt-6">
        <Card>
            <div class="flex flex-wrap items-center justify-between gap-2">
            <h1 class="text-xl font-semibold">Settings</h1>
            <Link
                :href="`/parties/${party.code}`"
                class="flex min-h-11 items-center rounded border border-border px-3 text-sm hover:text-primary"
            >Back to {{ party.name }}</Link>
            </div>
        </Card>

        <Card data-testid="general-section">
            <template #header>General</template>
            <form class="flex flex-col gap-4" data-testid="general-form" @submit.prevent="saveGeneral">
                <label class="flex flex-col gap-1 text-sm">
                    <span class="font-medium">Party name</span>
                    <input
                        v-model="generalForm.name"
                        type="text"
                        data-testid="setting-name"
                        class="min-h-11 rounded border border-border bg-surface px-2"
                    >
                    <span v-if="generalForm.errors.name" role="alert" data-testid="error-name" class="text-xs text-red-500">{{ generalForm.errors.name }}</span>
                </label>
                <div>
                    <button
                        type="submit"
                        :disabled="generalForm.processing"
                        data-testid="general-save"
                        class="min-h-11 rounded border border-border px-4 text-sm hover:text-primary disabled:opacity-50"
                    >Save general</button>
                </div>
            </form>
        </Card>

        <Card data-testid="playback-section">
            <template #header>Playback</template>
            <div class="flex flex-col gap-3">
            <div v-if="spotify_link_url">
                <Link
                    :href="spotify_link_url"
                    data-testid="spotify-link"
                    class="inline-flex min-h-11 items-center rounded border border-border px-3 text-sm hover:text-primary"
                >Link Spotify account</Link>
            </div>

            <form class="flex flex-col gap-4" data-testid="playlists-form" @submit.prevent="savePlaylists">
                <div v-for="field in playlistFields" :key="field.key" class="flex flex-col gap-1 text-sm">
                    <label :for="`playlist-${field.key}`" class="font-medium">{{ field.label }}</label>
                    <select
                        :id="`playlist-${field.key}`"
                        v-model="playlistForm[field.key]"
                        :data-testid="`select-${field.key}`"
                        class="min-h-11 rounded border border-border bg-surface px-2"
                    >
                        <option value="">{{ field.none }}</option>
                        <option v-for="playlist in playlists" :key="playlist.id" :value="playlist.id">{{ playlist.name }} ({{ playlist.track_count }})</option>
                    </select>
                    <input
                        v-model="playlistForm[field.key]"
                        type="text"
                        placeholder="Or paste a playlist link, URI or id"
                        :data-testid="`paste-${field.key}`"
                        class="min-h-11 rounded border border-border bg-surface px-2"
                    >
                    <span class="text-xs text-muted">{{ field.hint }}</span>
                    <span v-if="playlistForm.errors[field.key]" role="alert" :data-testid="`error-${field.key}`" class="text-xs text-red-500">{{ playlistForm.errors[field.key] }}</span>
                </div>
                <p
                    v-if="fallback_check"
                    data-testid="fallback-check"
                    :class="fallback_check.passes ? 'text-sm text-green-500' : 'text-sm text-red-500'"
                >{{ fallback_check.playable }} of {{ fallback_check.required }} required playable tracks</p>
                <div>
                    <button
                        type="submit"
                        :disabled="playlistForm.processing"
                        data-testid="playlists-save"
                        class="min-h-11 rounded border border-border px-4 text-sm hover:text-primary disabled:opacity-50"
                    >Save playlists</button>
                </div>
            </form>
            </div>
        </Card>

        <PartyPlayerSettings
            v-if="player_update_url"
            :player-kind="player_kind"
            :player-kinds="player_kinds"
            :playback-device="playback_device"
            :player-tokens-url="player_tokens_url"
            :devices-url="devices_url"
            :player-update-url="player_update_url"
            :spotify-link-url="spotify_link_url"
        />

        <Card data-testid="restrictions-section">
        <template #header>Requests and Restrictions</template>
        <form class="flex flex-col gap-4" data-testid="settings-form" @submit.prevent="save">

            <label class="flex min-h-11 items-center gap-3 text-sm">
                <input v-model="form.allow_requests" type="checkbox" data-testid="setting-allow_requests">
                <span>Accept requests</span>
            </label>

            <label v-for="field in numberFields" :key="field.key" class="flex flex-col gap-1 text-sm">
                <span class="font-medium">{{ field.label }}</span>
                <input
                    v-model="form[field.key]"
                    type="number"
                    min="0"
                    inputmode="numeric"
                    :data-testid="`setting-${field.key}`"
                    class="min-h-11 rounded border border-border bg-surface px-2"
                >
                <span class="text-xs text-muted">{{ field.hint }}</span>
                <span v-if="form.errors[field.key]" role="alert" :data-testid="`error-${field.key}`" class="text-xs text-red-500">{{ form.errors[field.key] }}</span>
            </label>

            <label class="flex min-h-11 items-center gap-3 text-sm">
                <input v-model="form.explicit" type="checkbox" data-testid="setting-explicit">
                <span>Allow explicit tracks</span>
            </label>

            <label class="flex min-h-11 items-center gap-3 text-sm">
                <input v-model="form.hold_requests" type="checkbox" data-testid="setting-hold_requests">
                <span>Hold new requests for approval</span>
            </label>

            <label class="flex min-h-11 items-center gap-3 text-sm">
                <input v-model="form.downvotes" type="checkbox" data-testid="setting-downvotes">
                <span>Allow downvotes</span>
            </label>

            <label v-if="form.downvotes" class="flex flex-col gap-1 text-sm" data-testid="downvotes-per-hour-field">
                <span class="font-medium">Downvotes per hour</span>
                <input
                    v-model="form.downvotes_per_hour"
                    type="number"
                    min="0"
                    inputmode="numeric"
                    data-testid="setting-downvotes_per_hour"
                    class="min-h-11 rounded border border-border bg-surface px-2"
                >
                <span class="text-xs text-muted">Leave empty for no limit.</span>
                <span v-if="form.errors.downvotes_per_hour" role="alert" data-testid="error-downvotes_per_hour" class="text-xs text-red-500">{{ form.errors.downvotes_per_hour }}</span>
            </label>

            <label class="flex flex-col gap-1 text-sm">
                <span class="flex min-h-11 items-center gap-3">
                    <input v-model="form.selection_mode" type="checkbox" true-value="weighted" false-value="deterministic" data-testid="setting-selection_mode">
                    <span class="font-medium">Raffle</span>
                </span>
                <span class="text-xs text-muted" data-testid="raffle-hint">Tracks with more upvotes are more likely to be played.</span>
            </label>

            <label class="flex min-h-11 items-center gap-3 text-sm">
                <input v-model="form.freeze_when_paused" type="checkbox" data-testid="setting-freeze_when_paused">
                <span>Freeze the queue while Paused</span>
            </label>

            <ModSlot name="settings" :party="party" :settings="settings" />

            <div>
                <button
                    type="submit"
                    :disabled="form.processing"
                    data-testid="settings-save"
                    class="min-h-11 rounded border border-border px-4 text-sm hover:text-primary disabled:opacity-50"
                >Save settings</button>
            </div>
        </form>
        </Card>

        <Card data-testid="display-section">
            <template #header>Display</template>
            <form class="flex flex-col gap-4" data-testid="display-form" @submit.prevent="saveDisplay">
                <label v-if="hasQrCodeSetting" class="flex min-h-11 items-center gap-3 text-sm">
                    <input v-model="displayForm.show_qrcode" type="checkbox" data-testid="setting-show_qrcode">
                    <span>Show the QR code and join link on the TV</span>
                </label>
                <div class="flex flex-wrap gap-3">
                    <button
                        v-if="hasQrCodeSetting"
                        type="submit"
                        :disabled="displayForm.processing"
                        data-testid="display-save"
                        class="min-h-11 rounded border border-border px-4 text-sm hover:text-primary disabled:opacity-50"
                    >Save display</button>
                    <Link
                        v-if="theme_url"
                        :href="theme_url"
                        data-testid="theme-link"
                        class="inline-flex min-h-11 items-center rounded border border-border px-3 text-sm hover:text-primary"
                    >Theme and TV layout</Link>
                </div>
            </form>
        </Card>

        <Card v-if="mods_url" data-testid="mods-section">
            <template #header>Mods</template>
            <Link
                :href="mods_url"
                data-testid="mods-link"
                class="inline-flex min-h-11 items-center rounded border border-border px-3 text-sm hover:text-primary"
            >Manage Mods</Link>
        </Card>
    </div>
</template>
