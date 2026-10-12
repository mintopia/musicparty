<script setup>
import {usePartyPresence} from '../../composables/usePartyPresence';
import {Head, Link, useForm, usePage} from '@inertiajs/vue3';
import {computed, provide} from 'vue';
import ModSlot from '../../Components/ModSlot.vue';

const props = defineProps({
    party: {type: Object, required: true},
    settings: {type: Object, required: true},
    enabled_mods: {type: Array, default: () => []},
    spotify_link_url: {type: String, default: null},
    playlists: {type: Array, default: () => []},
    fallback_playlist_id: {type: String, default: null},
    history_playlist_id: {type: String, default: null},
    fallback_check: {type: Object, default: null},
});

usePartyPresence(props.party.code);

provide('enabledMods', computed(() => props.enabled_mods));

const page = usePage();

const form = useForm({
    allow_requests: props.settings.allow_requests,
    max_requests: props.settings.max_requests,
    min_song_length: props.settings.min_song_length,
    max_song_length: props.settings.max_song_length,
    explicit: props.settings.explicit,
    hold_requests: props.settings.hold_requests,
    no_repeat_interval: props.settings.no_repeat_interval,
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
        <header class="flex flex-wrap items-center justify-between gap-2 rounded border border-border bg-surface px-5 py-5">
            <h1 class="text-xl font-semibold">Settings</h1>
            <Link
                :href="`/parties/${party.code}`"
                class="flex min-h-11 items-center rounded border border-border px-3 text-sm hover:text-primary"
            >Back to {{ party.name }}</Link>
        </header>

        <section class="flex flex-col gap-3 rounded border border-border bg-surface px-5 py-5" data-testid="playback-section">
            <h2 class="text-base font-bold">Playback</h2>
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
        </section>

        <form class="flex flex-col gap-4 rounded border border-border bg-surface px-5 py-5" data-testid="settings-form" @submit.prevent="save">
            <h2 class="text-base font-bold">Request rules</h2>

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
    </div>
</template>
