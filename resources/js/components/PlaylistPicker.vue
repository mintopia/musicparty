<script setup lang="ts">
import {computed, ref} from 'vue';
import type {NamedOption} from '@/lib/partyForm';

const props = defineProps<{
    id: string;
    playlists: NamedOption[];
    label?: string;
    invalid?: boolean;
}>();

const model = defineModel<string>({required: true});
const open = ref(false);
const query = ref('');

const selected = computed(() => props.playlists.find((playlist) => playlist.id === model.value));
const visible = computed(() => {
    const needle = query.value.trim().toLowerCase();
    return needle
        ? props.playlists.filter((playlist) => playlist.name.toLowerCase().includes(needle))
        : props.playlists;
});

function choose(playlist: NamedOption): void {
    model.value = playlist.id;
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
            <span class="mp-pick-label">{{ selected?.name ?? 'Choose a playlist' }}</span>
        </button>
        <div v-if="open" class="dropdown-menu show mp-pick-menu" role="listbox" :aria-label="label ?? 'Playlists'">
            <div class="mp-pick-head">
                <input
                    v-model="query"
                    type="text"
                    class="form-control"
                    placeholder="Search your playlists"
                    aria-label="Search your playlists"
                />
            </div>
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
                    <span class="mp-opt-name">{{ playlist.name }}</span>
                    <i v-if="playlist.id === model" class="icon ti ti-check" aria-hidden="true"></i>
                </button>
                <p v-if="visible.length === 0" class="mp-pick-empty mb-0">No playlists match.</p>
            </div>
        </div>
    </div>
</template>
