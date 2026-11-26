<script setup lang="ts">
import {Head} from '@inertiajs/vue3';
import EmptyState from '@/components/EmptyState.vue';
import ModPanel from '@/components/ModPanel.vue';
import PartyPlayer from '@/components/PartyPlayer.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {partyModUrl, partyModUsersUrl, partyModsUrl} from '@/lib/mods';
import type {LegacyPartyState, PartyMod} from '@/types';

defineProps<{
    party: {code: string; name: string};
    mods: PartyMod[];
    playerState: LegacyPartyState;
}>();
</script>

<template>
    <AppLayout :title="`Mods - ${party.name}`">
        <template #breadcrumbs>
            <li class="breadcrumb-item"><a :href="'/'">Home</a></li>
            <li class="breadcrumb-item">
                <a :href="`/parties/${party.code}`">{{ party.name }}</a>
            </li>
            <li class="breadcrumb-item active">
                <a :href="partyModsUrl(party.code)">Mods</a>
            </li>
        </template>
        <template #header>
            <PartyPlayer :code="party.code" :initial-state="playerState" />
        </template>
        <Head :title="`Mods - ${party.name}`" />
        <div class="page-header mt-0">
            <h1>Mods</h1>
        </div>
        <div class="row">
            <div class="col-12">
                <EmptyState
                    v-if="mods.length === 0"
                    title="No mods available"
                    description="There are no mods installed on this instance yet."
                />
                <div v-else class="accordion">
                    <ModPanel
                        v-for="mod in mods"
                        :key="mod.code"
                        :mod="mod"
                        :url="partyModUrl(party.code, mod.code)"
                        :user-search-url="partyModUsersUrl(party.code)"
                        show-enabled
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
