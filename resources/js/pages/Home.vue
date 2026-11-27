<script setup lang="ts">
import {Head, Link} from '@inertiajs/vue3';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import EmptyState from '@/components/EmptyState.vue';
import PartyJoinForm from '@/components/PartyJoinForm.vue';
import PartyList from '@/components/PartyList.vue';
import type {PartyRow} from '@/components/PartyList.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {create} from '@/routes/parties';

const props = defineProps<{parties: PartyRow[]}>();

const stageImage = props.parties.find((party) => party.cover)?.cover;
const stageStyle = stageImage ? {'--np-image': `url("${stageImage.replace(/["\\]/g, '\\$&')}")`} : undefined;
</script>

<template>
    <AppLayout title="Home">
        <template #breadcrumbs>
            <Breadcrumbs :items="[{label: 'Home'}]" />
        </template>
        <Head title="Home" />
        <div class="mp-narrow">
            <div class="mp-page-head mp-stage" :style="stageStyle" data-test="home-head">
                <div>
                    <h1>Your parties</h1>
                    <p>Parties you host or have joined.</p>
                </div>
                <Link :href="create().url" class="btn btn-outline-primary">
                    <i class="icon ti ti-playlist-add" aria-hidden="true"></i>Create party
                </Link>
            </div>
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card">
                        <h2 class="visually-hidden">Parties</h2>
                        <EmptyState
                            v-if="parties.length === 0"
                            title="You're not in any parties yet"
                            description="Enter the four-letter code from the TV, or open the link the host shared."
                            icon="ti-door-enter"
                        >
                            <Link :href="create().url" class="btn btn-outline-primary">
                                <i class="icon ti ti-playlist-add" aria-hidden="true"></i>Create party
                            </Link>
                        </EmptyState>
                        <PartyList v-else :parties="parties" />
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-body">
                            <h2 class="card-title mb-3">Join a party</h2>
                            <label class="form-label" for="party-code">Party code</label>
                            <PartyJoinForm input-id="party-code" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
