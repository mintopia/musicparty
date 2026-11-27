<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import {homeCrumbs} from '@/lib/breadcrumbs';
import {Head, useForm} from '@inertiajs/vue3';
import PartyForm from '@/components/PartyForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {initialPartyForm} from '@/lib/partyForm';
import type {NamedOption, PartyFormValues} from '@/lib/partyForm';
import {home} from '@/routes';
import {store} from '@/routes/parties';

const props = defineProps<{
    party: PartyFormValues;
    playlists: NamedOption[];
    devices: NamedOption[];
    providerIds: string[];
    defaultCountry: string;
    instanceTimezone: string;
    timezones: string[];
}>();

const form = useForm({
    provider_id: props.providerIds[0] ?? 'spotify',
    ...initialPartyForm(props.party, props.playlists, props.defaultCountry),
});

function submit(): void {
    form.post(store().url);
}
</script>

<template>
    <AppLayout title="Create Party">
        <template #breadcrumbs>
            <Breadcrumbs :items="[...homeCrumbs(), {label: 'Create Party'}]" />
        </template>
        <Head title="Create Party" />
        <div class="page-header mt-0">
            <h1>Create Party</h1>
        </div>

        <div class="col-md-6 offset-md-3">
            <PartyForm
                :form="form"
                :playlists="playlists"
                :devices="devices"
                :provider-ids="providerIds"
                :instance-timezone="instanceTimezone"
                :timezones="timezones"
                :cancel-url="home().url"
                @submit="submit"
            />
        </div>
    </AppLayout>
</template>
