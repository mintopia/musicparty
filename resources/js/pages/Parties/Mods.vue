<script setup lang="ts">
import {Head} from '@inertiajs/vue3';
import EmptyState from '@/components/EmptyState.vue';
import ModPanel from '@/components/ModPanel.vue';
import PartyPlayer from '@/components/PartyPlayer.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {partyModUrl, partyModUsersUrl, partyModsUrl} from '@/lib/mods';
import type {LegacyPartyState, PartyMod} from '@/types';

const props = defineProps<{
    party: {code: string; name: string};
    mods: PartyMod[];
    playerState: LegacyPartyState;
    holdsFlagged?: boolean;
    settingsUrl?: string;
}>();

const AI_REVIEW_MOD = 'ai-request-review';

function noticeFor(mod: PartyMod): {text: string; linkLabel: string; href: string} | null {
    if (mod.code !== AI_REVIEW_MOD || props.holdsFlagged !== false) {
        return null;
    }
    return {
        text: "Flagged requests aren't held. They go straight to the queue.",
        linkLabel: 'Turn on approval',
        href: props.settingsUrl ?? `/parties/${props.party.code}/edit`,
    };
}
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
        <div class="col-xl-8 offset-xl-2 col-lg-10 offset-lg-1">
            <div class="page-header mt-0">
                <h1>Mods</h1>
            </div>
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
                    :notice="noticeFor(mod)"
                    show-enabled
                />
            </div>
        </div>
    </AppLayout>
</template>
