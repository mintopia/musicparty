<script setup lang="ts">
import {Link} from '@inertiajs/vue3';
import StatePill from '@/components/StatePill.vue';
import type {PlaybackState} from '@/components/StatePill.vue';

export interface PartyRow {
    code: string;
    name: string;
    role: string;
    roleCode: string;
    playback: PlaybackState;
    detail: string | null;
    cover: string | null;
    canManage: boolean;
    url: string;
}

defineProps<{parties: PartyRow[]}>();

const roleBadges: Record<string, string> = {owner: 'bg-blue-lt', vip: 'bg-purple-lt', moderator: 'bg-purple-lt'};
</script>

<template>
    <div class="list-group list-group-flush mp-party-list" data-test="party-list">
        <div v-for="party in parties" :key="party.code" class="list-group-item d-flex align-items-center">
            <span
                class="avatar avatar-md rounded mp-party-cover"
                :style="party.cover ? {backgroundImage: `url('${party.cover}')`} : undefined"
                role="img"
                aria-label="Cover art"
            ></span>
            <div class="mp-party-info">
                <b>{{ party.name }}</b>
                <span class="mp-party-state">
                    <StatePill :state="party.playback" />
                    <template v-if="party.detail">{{ party.detail }}</template>
                </span>
            </div>
            <div class="mp-party-meta">
                <span class="badge" :class="roleBadges[party.roleCode] ?? 'bg-secondary-lt'">{{ party.role }}</span>
                <span class="mp-code small text-secondary" :aria-label="`Party code ${party.code}`">{{
                    party.code
                }}</span>
            </div>
            <Link :href="party.url" class="btn btn-sm btn-outline-secondary">
                {{ party.canManage ? 'Open Dashboard' : 'Open Queue' }}
            </Link>
        </div>
    </div>
</template>
