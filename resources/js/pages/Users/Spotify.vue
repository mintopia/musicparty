<script setup lang="ts">
import {computed} from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import {homeCrumbs} from '@/lib/breadcrumbs';
import {Head} from '@inertiajs/vue3';
import PageHead from '@/components/PageHead.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {create as linkAccount} from '@/routes/linkedaccounts';
import {create as createParty} from '@/routes/parties';

const props = defineProps<{
    spotify: {name: string} | null;
    search: {name: string} | null;
    spotifyEnabled: boolean;
    searchEnabled: boolean;
}>();

const missing = computed(() => [props.spotify ? null : 'Playback', props.search ? null : 'Search'].filter(Boolean));
</script>

<template>
    <AppLayout title="Spotify">
        <template #breadcrumbs>
            <Breadcrumbs :items="[...homeCrumbs(), {label: 'Spotify'}]" />
        </template>
        <Head title="Spotify" />
        <div class="mp-narrow">
            <PageHead title="Spotify" summary="Music Party uses two Spotify links. Each does one job." />

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="card mp-link-card h-100" data-test="link-playback">
                        <div class="card-body">
                            <div class="mp-link-head">
                                <span
                                    class="avatar avatar-md rounded"
                                    :class="spotify ? 'bg-green-lt text-green' : 'bg-secondary-lt'"
                                >
                                    <i class="icon ti ti-music-bolt"></i>
                                </span>
                                <h2 class="h3 card-title mb-0">Playback</h2>
                            </div>
                            <p>Lets Music Party play on your Spotify for parties you host.</p>
                            <div class="mp-link-state">
                                <template v-if="spotify">
                                    <span class="status-dot status-green"></span>Linked as {{ spotify.name }}
                                </template>
                                <template v-else><span class="status-dot status-secondary"></span>Not linked</template>
                            </div>
                            <a
                                v-if="spotifyEnabled"
                                class="btn"
                                :class="spotify ? 'btn-outline-secondary' : 'btn-success'"
                                :href="linkAccount('spotify').url"
                            >
                                <i class="icon ti" :class="spotify ? 'ti-refresh' : 'ti-brand-spotify'"></i>
                                {{ spotify ? 'Re-link' : 'Link Spotify for playback' }}
                            </a>
                            <p v-else class="text-secondary mb-0" data-test="link-playback-disabled">
                                Linking is disabled by an administrator.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card mp-link-card h-100" data-test="link-search">
                        <div class="card-body">
                            <div class="mp-link-head">
                                <span
                                    class="avatar avatar-md rounded"
                                    :class="search ? 'bg-green-lt text-green' : 'bg-secondary-lt'"
                                >
                                    <i class="icon ti ti-music-search"></i>
                                </span>
                                <h2 class="h3 card-title mb-0">Search</h2>
                            </div>
                            <p>Lets guests search Spotify's catalogue.</p>
                            <div class="mp-link-state">
                                <template v-if="search">
                                    <span class="status-dot status-green"></span>Linked as {{ search.name }}
                                </template>
                                <template v-else><span class="status-dot status-secondary"></span>Not linked</template>
                            </div>
                            <a
                                v-if="searchEnabled"
                                class="btn"
                                :class="search ? 'btn-outline-secondary' : 'btn-success'"
                                :href="linkAccount('spotifysearch').url"
                            >
                                <i class="icon ti" :class="search ? 'ti-refresh' : 'ti-brand-spotify'"></i>
                                {{ search ? 'Re-link' : 'Link Spotify for search' }}
                            </a>
                            <p v-else class="text-secondary mb-0" data-test="link-search-disabled">
                                Linking is disabled by an administrator.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body d-flex flex-wrap align-items-center gap-3">
                    <div class="flex-fill">
                        <template v-if="spotify && search">
                            <h2 class="h3 mb-1">Ready to host</h2>
                            <p class="text-secondary mb-0">Both links are working. Create a party whenever you like.</p>
                        </template>
                        <template v-else>
                            <h2 class="h3 mb-1">Link both to host a party</h2>
                            <p class="text-secondary mb-0" data-test="link-missing">
                                {{ missing.join(' and ') }}
                                {{ missing.length === 1 ? 'still needs' : 'still need' }} linking.
                            </p>
                        </template>
                    </div>
                    <a v-if="spotify && search" :href="createParty().url" class="btn btn-primary">
                        <i class="icon ti ti-playlist-add"></i>
                        Create party
                    </a>
                    <button v-else class="btn btn-primary" disabled>
                        <i class="icon ti ti-playlist-add"></i>
                        Create party
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
