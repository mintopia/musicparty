<script setup lang="ts">
import {Head, useForm} from '@inertiajs/vue3';
import PartyForm from '@/components/PartyForm.vue';
import PartyPlayer from '@/components/PartyPlayer.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {initialPartyForm} from '@/lib/partyForm';
import type {NamedOption, PartyFormValues} from '@/lib/partyForm';
import {home} from '@/routes';
import {edit, show, update} from '@/routes/parties';
import type {LegacyPartyState} from '@/types';

const props = defineProps<{
    party: PartyFormValues & {code: string};
    providerName: string;
    playlists: NamedOption[];
    devices: NamedOption[];
    defaultCountry: string;
    instanceTimezone: string;
    timezones: string[];
    playerState: LegacyPartyState;
    encore: {host: string; key: string | null; channel: string; secret: string | null};
}>();

const form = useForm(initialPartyForm(props.party, props.playlists, props.defaultCountry));

function submit(): void {
    form.patch(update(props.party.code).url);
}
</script>

<template>
    <AppLayout title="Settings">
        <template #breadcrumbs>
            <li class="breadcrumb-item"><a :href="home().url">Home</a></li>
            <li class="breadcrumb-item">
                <a :href="show(party.code).url">{{ party.name }}</a>
            </li>
            <li class="breadcrumb-item active">
                <a :href="edit(party.code).url">Settings</a>
            </li>
        </template>
        <template #header>
            <PartyPlayer :code="party.code" :initial-state="playerState" />
        </template>
        <Head title="Settings" />
        <div class="page-header mt-0">
            <h1>Settings</h1>
        </div>

        <div class="col-md-6 offset-md-3">
            <PartyForm
                :form="form"
                :playlists="playlists"
                :devices="devices"
                :provider-name="providerName"
                :instance-timezone="instanceTimezone"
                :timezones="timezones"
                :cancel-url="show(party.code).url"
                @submit="submit"
            />
            <div id="encore-relay" class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title">Encore relay</h3>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Relay URL</dt>
                        <dd class="col-sm-8">
                            <code>wss://{{ encore.host }}/app/{{ encore.key }}</code>
                        </dd>
                        <dt class="col-sm-4">Pusher protocol</dt>
                        <dd class="col-sm-8">On</dd>
                        <dt class="col-sm-4">Channel</dt>
                        <dd class="col-sm-8">
                            <code>{{ encore.channel }}</code>
                        </dd>
                        <dt class="col-sm-4">App key</dt>
                        <dd class="col-sm-8">
                            <code>{{ encore.key }}</code>
                        </dd>
                        <template v-if="encore.secret">
                            <dt class="col-sm-4">App secret</dt>
                            <dd class="col-sm-8">
                                <code>{{ encore.secret }}</code>
                            </dd>
                        </template>
                    </dl>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
