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
    playback: 'playing' | 'paused' | 'idle';
    track: string | null;
    canManage: boolean;
    url: string;
}

defineProps<{parties: PartyRow[]}>();

const playbackLabels = {playing: 'Playing', paused: 'Paused', idle: 'Nothing playing'};
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
        <div class="page-header mt-0 d-flex align-items-center justify-content-between">
            <div>
                <h1>Parties</h1>
                <p class="text-secondary mb-0">Parties you host or have joined.</p>
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
                    <div v-else class="list-group list-group-flush" data-test="party-list">
                        <div
                            v-for="party in parties"
                            :key="party.code"
                            class="list-group-item d-flex align-items-center flex-wrap gap-3"
                        >
                            <div class="flex-fill">
                                <b class="d-block">{{ party.name }}</b>
                                <span class="text-secondary small">
                                    {{ playbackLabels[party.playback] }}
                                    <template v-if="party.track"> · {{ party.track }}</template>
                                </span>
                            </div>
                            <span class="badge bg-blue-lt">{{ party.role }}</span>
                            <span class="fw-bold text-secondary small" :aria-label="`Party code ${party.code}`">{{
                                party.code
                            }}</span>
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
                        <div class="input-group">
                            <input
                                id="party-code"
                                v-model="code"
                                class="form-control text-uppercase"
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
    </AppLayout>
</template>
