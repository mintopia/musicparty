<script setup lang="ts">
import {computed, ref} from 'vue';
import type {PlaylistOption} from '@/lib/partyForm';

const props = defineProps<{
    id: string;
    playlists: PlaylistOption[];
    hostName?: string;
    providerName?: string;
    invalid?: boolean;
}>();

const model = defineModel<string>({required: true});
const custom = defineModel<string>('custom', {default: ''});
const open = ref(false);
const query = ref('');

const heading = computed(() => `${props.hostName ?? 'Your'}${props.hostName ? "'s" : ''} ${props.providerName ?? 'Spotify'} playlists`);
const selected = computed(() => props.playlists.find((playlist) => playlist.id === model.value));
const visible = computed(() => {
    const needle = query.value.trim().toLowerCase();
    return needle
        ? props.playlists.filter((playlist) => playlist.name.toLowerCase().includes(needle))
        : props.playlists;
});

const tracks = (count?: number): string => `${count ?? 0} ${count === 1 ? 'track' : 'tracks'}`;

function choose(playlist: PlaylistOption): void {
    model.value = playlist.id;
    open.value = false;
    query.value = '';
}

function chooseLink(): void {
    model.value = 'other';
    open.value = false;
    query.value = '';
}
</script>

<template>
    <div class="mp-pick" data-test="playlist-picker">
        <button
            :id="id"
            type="button"
            class="form-select text-start"
            :class="{'is-invalid': invalid}"
            aria-haspopup="listbox"
            :aria-expanded="open"
            @click="open = !open"
        >
            <template v-if="selected">
                <img v-if="selected.imageUrl" class="mp-cover" :src="selected.imageUrl" alt="" width="32" height="32" />
                <span v-else class="mp-cover mp-cover-empty" aria-hidden="true"></span>
                <span class="mp-pick-label">{{ selected.name }}</span>
                <small>{{ tracks(selected.trackCount) }}</small>
            </template>
            <span v-else class="mp-pick-label">{{
                model === 'other' ? 'Playlist link or ID' : 'Choose a playlist'
            }}</span>
        </button>
        <div v-if="open" class="dropdown-menu show mp-pick-menu" role="listbox" :aria-label="heading">
            <div class="mp-pick-head">
                <input
                    v-model="query"
                    type="text"
                    class="form-control"
                    role="combobox"
                    aria-expanded="true"
                    placeholder="Search your playlists"
                    aria-label="Search your playlists"
                />
            </div>
            <div class="mp-pick-meta" data-test="playlist-heading">{{ heading }}</div>
            <div class="mp-pick-list">
                <button
                    v-for="playlist in visible"
                    :key="playlist.id"
                    type="button"
                    class="mp-opt"
                    role="option"
                    data-test="playlist-option"
                    :aria-selected="playlist.id === model"
                    @click="choose(playlist)"
                >
                    <img v-if="playlist.imageUrl" :src="playlist.imageUrl" alt="" width="36" height="36" />
                    <span v-else class="mp-cover-empty" aria-hidden="true"></span>
                    <span class="mp-opt-text">
                        <span class="mp-opt-name">{{ playlist.name }}</span>
                        <small>{{ tracks(playlist.trackCount) }}</small>
                    </span>
                    <i v-if="playlist.id === model" class="icon ti ti-check" aria-hidden="true"></i>
                </button>
                <p v-if="visible.length === 0" class="mp-pick-empty mb-0">No playlists match.</p>
            </div>
            <div class="mp-pick-foot">
                <button
                    type="button"
                    class="mp-opt"
                    role="option"
                    data-test="playlist-link-option"
                    :aria-selected="model === 'other'"
                    @click="chooseLink"
                >
                    <i class="icon ti ti-link" aria-hidden="true"></i>
                    <span class="mp-opt-text">
                        <span class="mp-opt-name">Use a playlist link or ID</span>
                        <small>For a playlist that isn't in {{ hostName ? `${hostName}'s` : 'your' }} library</small>
                    </span>
                    <i v-if="model === 'other'" class="icon ti ti-check" aria-hidden="true"></i>
                </button>
            </div>
        </div>
        <input
            v-if="model === 'other'"
            id="custom_backup_playlist_id"
            v-model="custom"
            type="text"
            name="custom_backup_playlist_id"
            class="form-control mt-2"
            :class="{'is-invalid': invalid}"
            placeholder="Paste a Spotify playlist link or ID"
            aria-label="Playlist link or ID"
            data-test="playlist-link-input"
        />
    </div>
</template>
