<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import {homeCrumbs} from '@/lib/breadcrumbs';
import {Head} from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import {create as linkAccount} from '@/routes/linkedaccounts';
import {create as createParty} from '@/routes/parties';

defineProps<{spotify: {name: string} | null; search: {name: string} | null}>();
</script>

<template>
    <AppLayout title="Link Spotify Account">
        <template #breadcrumbs>
            <Breadcrumbs :items="[...homeCrumbs(), {label: 'Link Spotify Account'}]" />
        </template>
        <Head title="Link Spotify Account" />
        <div class="page-header mt-2">
            <h1>Link Spotify Account</h1>
        </div>

        <p>
            For Music Party to work, we need to link your Spotify account twice. The first link is for controlling the
            party and the second link is for guests to search for music.
        </p>

        <div class="row row-deck">
            <div class="col-md-3 offset-md-3">
                <div class="card">
                    <div class="card-body">
                        <template v-if="spotify">
                            <p class="text-center">
                                <i class="icon ti ti-music-bolt icon-lg text-success m-6"></i>
                            </p>
                            <div class="mt-2 text-center">{{ spotify.name }}</div>
                        </template>
                        <template v-else>
                            <p class="text-center">
                                <i class="icon ti ti-music-bolt icon-lg text-muted m-6"></i>
                            </p>
                            <div class="mt-2">
                                <a class="btn btn-success w-100" :href="linkAccount('spotify').url">
                                    <i class="icon ti ti-brand-spotify"></i>
                                    Link with Spotify
                                </a>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <template v-if="search">
                            <p class="text-center">
                                <i class="icon ti ti-music-search icon-lg text-success m-6"></i>
                            </p>
                            <div class="mt-2 text-center">{{ search.name }}</div>
                        </template>
                        <template v-else>
                            <p class="text-center">
                                <i class="icon ti ti-music-search icon-lg text-muted m-6"></i>
                            </p>
                            <div class="mt-2">
                                <a class="btn btn-success w-100" :href="linkAccount('spotifysearch').url">
                                    <i class="icon ti ti-brand-spotify"></i>
                                    Link with Spotify (Search)
                                </a>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 offset-md-3 mt-2">
                <a v-if="spotify && search" :href="createParty().url" class="btn btn-primary w-100">
                    <i class="icon ti ti-playlist-add"></i>
                    Create Party
                </a>
                <button v-else class="btn btn-primary w-100" disabled>
                    <i class="icon ti ti-playlist-add"></i>
                    Create Party
                </button>
            </div>
        </div>
    </AppLayout>
</template>
