<script setup lang="ts">
import {computed} from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import {homeCrumbs} from '@/lib/breadcrumbs';
import {Head} from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import {create as linkAccount} from '@/routes/linkedaccounts';
import {create as createParty} from '@/routes/parties';

const props = defineProps<{spotify: {name: string} | null; search: {name: string} | null}>();

const missing = computed(() => [props.spotify ? null : 'Playback', props.search ? null : 'Search'].filter(Boolean));
</script>

<template>
    <AppLayout title="Spotify">
        <template #breadcrumbs>
            <Breadcrumbs :items="[...homeCrumbs(), {label: 'Spotify'}]" />
        </template>
        <Head title="Spotify" />
        <div class="page-header mt-2">
            <h1>Spotify</h1>
            <p class="text-secondary">Music Party uses two Spotify links. Each does one job.</p>
        </div>

        <div class="row row-deck g-3">
            <div class="col-md-6">
                <div class="card" data-test="link-playback">
                    <div class="card-body">
                        <h2 class="h3">Playback</h2>
                        <p class="text-secondary">Lets Music Party play on your Spotify for parties you host.</p>
                        <p v-if="spotify" class="mb-3">
                            <span class="status-dot status-green me-2"></span>Linked as {{ spotify.name }}
                        </p>
                        <p v-else class="mb-3"><span class="status-dot me-2"></span>Not linked</p>
                        <a
                            class="btn"
                            :class="spotify ? 'btn-outline-secondary' : 'btn-success'"
                            :href="linkAccount('spotify').url"
                        >
                            <i class="icon ti ti-brand-spotify"></i>
                            {{ spotify ? 'Re-link' : 'Link Spotify for playback' }}
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card" data-test="link-search">
                    <div class="card-body">
                        <h2 class="h3">Search</h2>
                        <p class="text-secondary">Lets guests search Spotify's catalogue.</p>
                        <p v-if="search" class="mb-3">
                            <span class="status-dot status-green me-2"></span>Linked as {{ search.name }}
                        </p>
                        <p v-else class="mb-3"><span class="status-dot me-2"></span>Not linked</p>
                        <a
                            class="btn"
                            :class="search ? 'btn-outline-secondary' : 'btn-success'"
                            :href="linkAccount('spotifysearch').url"
                        >
                            <i class="icon ti ti-brand-spotify"></i>
                            {{ search ? 'Re-link' : 'Link Spotify for search' }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="mt-3">
            <a v-if="spotify && search" :href="createParty().url" class="btn btn-primary">
                <i class="icon ti ti-playlist-add"></i>
                Create party
            </a>
            <template v-else>
                <button class="btn btn-primary" disabled>
                    <i class="icon ti ti-playlist-add"></i>
                    Create party
                </button>
                <p class="text-secondary mt-2 mb-0" data-test="link-missing">
                    Link both to host a party. {{ missing.join(' and ') }}
                    {{ missing.length === 1 ? 'still needs' : 'still need' }} linking.
                </p>
            </template>
        </div>
    </AppLayout>
</template>
