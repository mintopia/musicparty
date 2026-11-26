<script setup lang="ts">
import {Head, Link, useForm} from '@inertiajs/vue3';
import ModerationBreadcrumbs from '@/components/ModerationBreadcrumbs.vue';
import ModerationForm from '@/components/ModerationForm.vue';
import PartyPlayer from '@/components/PartyPlayer.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type {LegacyPartyState} from '@/types';

interface TrackData {
    id: string;
    name: string;
    isrc: string | null;
    album: {id: string; name: string};
    artists: {id: string; name: string}[];
}

const props = defineProps<{
    party: {code: string; name: string; canManage: boolean};
    playerState: LegacyPartyState;
    types: Record<string, string>;
    actions: Record<string, string>;
    moderation: {
        type: string | null;
        action: string;
        value: string | null;
        enabled: boolean;
        regex: boolean;
        notes: string | null;
    };
    track: TrackData | null;
}>();

const form = useForm({
    type: props.moderation.type ?? '',
    action: props.moderation.action,
    value: props.moderation.value ?? '',
    enabled: props.moderation.enabled,
    regex: props.moderation.regex,
    notes: props.moderation.notes ?? '',
});

function submit(): void {
    form.post(`/parties/${props.party.code}/moderation`);
}
</script>

<template>
    <AppLayout :title="`Add rule - ${party.name}`">
        <template #breadcrumbs>
            <ModerationBreadcrumbs :party="party">
                <li class="breadcrumb-item active">
                    <a :href="`/parties/${party.code}/moderation/create`">Add rule</a>
                </li>
            </ModerationBreadcrumbs>
        </template>
        <template #header>
            <PartyPlayer :code="party.code" :initial-state="playerState" />
        </template>
        <Head :title="`Add rule - ${party.name}`" />
        <div class="col-xl-8 offset-xl-2 col-lg-10 offset-lg-1">
            <div class="page-header mt-0">
                <h1>Add rule</h1>
            </div>
            <div class="row">
                <div :class="track ? 'col-12 col-lg-6' : 'col-12'">
                    <form class="card" @submit.prevent="submit">
                        <ModerationForm :form="form" :types="types" :actions="actions" />
                        <div class="card-footer text-end">
                            <div class="d-flex">
                                <Link :href="`/parties/${party.code}/moderation`" class="btn btn-link">Cancel</Link>
                                <button type="submit" class="btn btn-primary ms-auto" :disabled="form.processing">
                                    Save
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <div v-if="track" class="col-12 col-lg-6" data-test="track-data">
                    <h3>Track Data</h3>

                    <div class="datagrid">
                        <div>
                            <div class="datagrid-title">Track ID</div>
                            <div>{{ track.id ?? 'None' }}</div>
                        </div>
                    </div>
                    <div class="datagrid mt-3">
                        <div>
                            <div class="datagrid-title">Track Name</div>
                            <div>{{ track.name ?? 'None' }}</div>
                        </div>
                    </div>
                    <div class="datagrid mt-3">
                        <div>
                            <div class="datagrid-title">Album ID</div>
                            <div>{{ track.album.id ?? 'None' }}</div>
                        </div>
                    </div>
                    <div class="datagrid mt-3">
                        <div>
                            <div class="datagrid-title">Album Name</div>
                            <div>{{ track.album.name ?? 'None' }}</div>
                        </div>
                    </div>
                    <template v-for="artist in track.artists" :key="artist.id">
                        <div class="datagrid mt-3">
                            <div>
                                <div class="datagrid-title">Artist ID</div>
                                <div>{{ artist.id ?? 'None' }}</div>
                            </div>
                        </div>
                        <div class="datagrid mt-3">
                            <div>
                                <div class="datagrid-title">Artist Name</div>
                                <div>{{ artist.name ?? 'None' }}</div>
                            </div>
                        </div>
                    </template>

                    <div class="datagrid mt-3">
                        <div>
                            <div class="datagrid-title">ISRC Code</div>
                            <div>{{ track.isrc ?? 'None' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
