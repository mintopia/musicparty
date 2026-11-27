<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import EmptyState from '@/components/EmptyState.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {homeCrumbs} from '@/lib/breadcrumbs';
import {create, show} from '@/routes/parties';
import {Head, Link, router} from '@inertiajs/vue3';
import {ref} from 'vue';

interface PartyRow {
    code: string;
    name: string;
    role: string;
    roleCode: string;
    playback: 'playing' | 'paused' | 'idle' | 'stale';
    detail: string | null;
    cover: string | null;
    canManage: boolean;
    url: string;
}

defineProps<{parties: PartyRow[]}>();

const playbackLabels = {playing: 'Playing', paused: 'Paused', idle: 'Nothing playing', stale: 'Player stale'};
const roleBadges: Record<string, string> = {owner: 'bg-blue-lt', vip: 'bg-purple-lt'};
const code = ref('');
const error = ref('');

function join(): void {
    if (!/^[A-Z]{4}$/.test(code.value)) {
        error.value = 'Party codes are four letters.';
        return;
    }
    error.value = '';
    router.get(show(code.value).url);
}
</script>

<template>
    <AppLayout title="Parties">
        <template #breadcrumbs>
            <Breadcrumbs :items="[...homeCrumbs(), {label: 'Parties'}]" />
        </template>
        <Head title="Parties" />
        <div class="parties-narrow">
            <div class="parties-head">
                <div>
                    <h1>Parties</h1>
                    <p>Parties you host or have joined.</p>
                </div>
                <Link :href="create().url" class="btn btn-outline-primary">
                    <i class="icon ti ti-playlist-add"></i>Create party
                </Link>
            </div>
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card">
                        <h2 class="visually-hidden">Parties</h2>
                        <EmptyState
                            v-if="parties.length === 0"
                            title="You're not in any parties yet"
                            description="Enter a party code to join one, or create your own."
                            icon="ti-playlist"
                        />
                        <div v-else class="list-group list-group-flush party-list" data-test="party-list">
                            <div v-for="party in parties" :key="party.code" class="list-group-item d-flex align-items-center">
                                <span
                                    class="avatar avatar-md rounded party-cover"
                                    :style="party.cover ? {backgroundImage: `url('${party.cover}')`} : undefined"
                                    role="img"
                                    aria-label="Cover art"
                                ></span>
                                <div class="party-info">
                                    <b>{{ party.name }}</b>
                                    <span class="party-state">
                                        <span class="party-pill" :class="`is-${party.playback}`">
                                            <span v-if="party.playback === 'playing'" class="party-eq" aria-hidden="true"
                                                ><i></i><i></i><i></i
                                            ></span>
                                            {{ playbackLabels[party.playback] }}
                                        </span>
                                        <template v-if="party.detail">{{ party.detail }}</template>
                                    </span>
                                </div>
                                <div class="party-meta">
                                    <span class="badge" :class="roleBadges[party.roleCode] ?? 'bg-secondary-lt'">{{
                                        party.role
                                    }}</span>
                                    <span class="party-code small text-secondary" :aria-label="`Party code ${party.code}`">{{
                                        party.code
                                    }}</span>
                                </div>
                                <Link :href="party.url" class="btn btn-sm btn-outline-secondary">
                                    {{ party.canManage ? 'Open Dashboard' : 'Open Queue' }}
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <form class="card" @submit.prevent="join">
                        <div class="card-body">
                            <h2 class="card-title mb-3">Join a party</h2>
                            <label class="form-label" for="party-code">Party code</label>
                            <div class="d-flex gap-2 align-items-center">
                                <input
                                    id="party-code"
                                    v-model="code"
                                    class="party-code-input"
                                    :class="{'is-invalid': error}"
                                    maxlength="4"
                                    placeholder="KXQB"
                                    autocapitalize="characters"
                                    autocomplete="off"
                                    aria-describedby="party-code-hint"
                                    @input="code = code.toUpperCase()"
                                />
                                <button type="submit" class="btn btn-primary">Join</button>
                            </div>
                            <div v-if="error" class="invalid-feedback d-block">{{ error }}</div>
                            <div id="party-code-hint" class="form-hint mt-2">
                                Four letters. It's on the TV, or ask the host.
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.parties-narrow {
    max-inline-size: 960px;
    margin-inline: auto;
}
.parties-head {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    justify-content: space-between;
    gap: 12px 24px;
    margin-block-end: 24px;
}
.parties-head h1 {
    margin: 0;
    font-size: 2.5rem;
    font-weight: 800;
    line-height: 1.05;
    letter-spacing: -0.03em;
}
.parties-head p {
    margin: 8px 0 0;
    color: var(--tblr-secondary-color);
    font-size: 1rem;
}
@media (max-width: 575.98px) {
    .parties-head h1 {
        font-size: 2rem;
    }
}
.party-list .list-group-item {
    flex-wrap: wrap;
    gap: 12px 16px;
}
.party-cover {
    background-image: linear-gradient(135deg, var(--tblr-blue), var(--tblr-purple) 55%, var(--tblr-orange));
    background-size: cover;
    background-position: center;
}
.party-info {
    flex: 1 1 200px;
    min-width: 0;
}
.party-info b {
    display: block;
    font-size: 1rem;
}
.party-state {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 4px 8px;
    margin-block-start: 4px;
    color: var(--tblr-secondary-color);
    font-size: 0.8125rem;
}
.party-meta {
    display: flex;
    align-items: center;
    gap: 10px;
}
.party-code {
    font-family: var(--tblr-font-monospace);
    letter-spacing: 0.2em;
    font-weight: 700;
}
.party-pill {
    --pill: var(--tblr-secondary);
    display: inline-flex;
    align-items: center;
    gap: 0.45em;
    padding: 2px 10px 2px 8px;
    border-radius: 999px;
    background: color-mix(in oklab, var(--pill) 14%, transparent);
    color: color-mix(in oklab, var(--pill) 75%, var(--tblr-body-color));
    font-size: 0.8125rem;
    font-weight: 650;
    white-space: nowrap;
}
.party-pill::before {
    content: '';
    inline-size: 8px;
    block-size: 8px;
    border-radius: 50%;
    background: var(--pill);
}
.party-pill.is-playing {
    --pill: var(--tblr-success);
}
.party-pill.is-paused {
    --pill: var(--tblr-warning);
}
.party-pill.is-stale {
    --pill: var(--tblr-danger);
}
.party-pill.is-playing::before {
    display: none;
}
.party-eq {
    display: inline-flex;
    align-items: flex-end;
    gap: 2px;
    block-size: 10px;
}
.party-eq > i {
    inline-size: 3px;
    block-size: 100%;
    border-radius: 1px;
    background: var(--pill);
    transform-origin: bottom;
    animation: party-eq 1.1s ease-in-out infinite alternate;
}
.party-eq > i:nth-child(2) {
    animation-delay: -0.4s;
}
.party-eq > i:nth-child(3) {
    animation-delay: -0.75s;
}
@keyframes party-eq {
    0% {
        transform: scaleY(0.3);
    }
    100% {
        transform: scaleY(1);
    }
}
@media (prefers-reduced-motion: reduce) {
    .party-eq > i {
        animation: none;
    }
}
.party-code-input {
    flex: none;
    inline-size: 9ch;
    min-block-size: 56px;
    padding: 0 12px;
    border: 1px solid var(--tblr-border-color);
    border-radius: var(--tblr-border-radius);
    background: var(--tblr-bg-forms, var(--tblr-bg-surface));
    color: var(--tblr-body-color);
    font: inherit;
    font-size: 1.5rem;
    font-weight: 700;
    letter-spacing: 0.25em;
    text-align: center;
    text-transform: uppercase;
}
.party-code-input:focus {
    border-color: var(--tblr-primary);
    outline: 0;
    box-shadow: 0 0 0 0.25rem color-mix(in oklab, var(--tblr-primary) 25%, transparent);
}
.party-code-input.is-invalid {
    border-color: var(--tblr-danger);
}
</style>
