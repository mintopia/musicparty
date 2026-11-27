<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import {homeCrumbs} from '@/lib/breadcrumbs';
import {Head, Link, useForm} from '@inertiajs/vue3';
import {computed, ref, watch} from 'vue';
import CopyButton from '@/components/CopyButton.vue';
import PlaylistPicker from '@/components/PlaylistPicker.vue';
import OptionPicker from '@/components/OptionPicker.vue';
import SettingRow from '@/components/SettingRow.vue';
import StageSurface from '@/components/StageSurface.vue';
import SuccessMark from '@/components/SuccessMark.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {countryOptionsFor} from '@/lib/countries';
import {timezoneOffsetLabel} from '@/lib/timezones';
import {initialCreateForm} from '@/lib/partyForm';
import type {NamedOption, PartyFormValues, PlaylistOption} from '@/lib/partyForm';
import {dashboard, edit, store, tv} from '@/routes/parties';

interface CreatedParty {
    code: string;
    name: string;
    joinUrl: string;
    qr: string;
}

const props = defineProps<{
    party: PartyFormValues;
    playlists: PlaylistOption[];
    hostName?: string;
    devices: NamedOption[];
    providerIds: string[];
    defaultCountry: string;
    instanceTimezone: string;
    timezones: string[];
    createdParty?: CreatedParty | null;
}>();

const DEFAULT_MAX_REQUESTS = 3;
const STEPS = ['Name', 'Music', 'Defaults'];
const STEP_FIELDS: Record<number, string[]> = {
    1: ['name', 'provider_id'],
    2: ['backup_playlist_id', 'custom_backup_playlist_id', 'device_id', 'history_playlist_id'],
};

const form = useForm({
    provider_id: props.providerIds[0] ?? 'spotify',
    ...initialCreateForm(props.party, props.playlists, props.defaultCountry),
    custom_backup_playlist_id: '',
});
form.max_requests = props.party.max_requests ?? DEFAULT_MAX_REQUESTS;

const step = ref(1);
const nameError = ref('');
const countryOptions = computed(() =>
    countryOptionsFor(form.country).map((option) => ({value: option.code, label: option.name, meta: option.code})),
);
const timezoneOptions = computed(() => [
    {value: '', label: `Use instance timezone (${props.instanceTimezone})`},
    ...props.timezones.map((identifier) => ({
        value: identifier,
        label: identifier,
        meta: timezoneOffsetLabel(identifier),
    })),
]);
const ucfirst = (value: string): string => value.charAt(0).toUpperCase() + value.slice(1);

const switches: {name: 'allow_requests' | 'downvotes' | 'explicit' | 'flag_requests'; label: string; hint?: string}[] =
    [
        {name: 'allow_requests', label: 'Accept requests'},
        {name: 'downvotes', label: 'Allow downvotes'},
        {name: 'explicit', label: 'Allow explicit tracks'},
        {
            name: 'flag_requests',
            label: 'Hold Flagged Requests for approval',
            hint: 'They wait in Moderation until a manager or moderator decides',
        },
    ];

function err(field: string): string | undefined {
    return (form.errors as Record<string, string | undefined>)[field];
}

function stepOf(field: string): number {
    return Number(Object.keys(STEP_FIELDS).find((key) => STEP_FIELDS[Number(key)]!.includes(field)) ?? 3);
}

function nameMissing(): boolean {
    nameError.value = form.name.trim() === '' ? 'Enter a name for your party.' : '';
    if (nameError.value) {
        step.value = 1;
    }
    return nameError.value !== '';
}

function submit(): void {
    if (nameMissing()) {
        return;
    }
    form.post(store().url);
}

function next(): void {
    if (step.value === 1 && nameMissing()) {
        return;
    }
    step.value = Math.min(step.value + 1, 3);
}

function onSubmit(): void {
    if (step.value === 3) {
        submit();
    } else {
        next();
    }
}

watch(
    () => Object.keys(form.errors).join(','),
    () => {
        const fields = Object.keys(form.errors);
        if (fields.length > 0) {
            step.value = Math.min(...fields.map(stepOf));
        }
    },
    {immediate: true},
);

const spokenCode = computed(() => (props.createdParty?.code ?? '').split('').join(' '));
</script>

<template>
    <AppLayout title="Create Party">
        <template #breadcrumbs>
            <Breadcrumbs :items="[...homeCrumbs(), {label: 'Create Party'}]" />
        </template>
        <Head title="Create Party" />
        <div class="mp-wizard">
            <div class="mp-page-head">
                <div v-if="createdParty">
                    <h1>{{ createdParty.name }} is ready</h1>
                    <p>Share the code, or put the TV on the big screen.</p>
                </div>
                <div v-else>
                    <h1>Create a party</h1>
                    <p>Only a name is required. Everything else can be changed later in Settings.</p>
                </div>
            </div>
            <ol class="mp-steps" aria-label="Create party steps" data-test="steps">
                <li
                    v-for="(label, index) in STEPS"
                    :key="label"
                    :class="{'is-done': createdParty || index + 1 < step}"
                    :aria-current="!createdParty && index + 1 === step ? 'step' : undefined"
                >
                    {{ label }}
                </li>
            </ol>

            <div v-if="createdParty" class="card mp-done-card" data-test="done">
                <StageSurface>
                    <div class="mp-done">
                        <div>
                            <SuccessMark />
                            <div class="mp-stage-sub mb-1">Party code</div>
                            <div class="mp-done-code" :aria-label="spokenCode" data-test="done-code">
                                {{ createdParty.code }}
                            </div>
                            <div class="text-secondary mt-4 mb-1">Join link</div>
                            <div class="d-flex align-items-center gap-2">
                                <code class="fs-4">{{ createdParty.joinUrl }}</code>
                                <CopyButton :value="createdParty.joinUrl" label="Copy join link" />
                            </div>
                            <p class="text-secondary mt-3 mb-0">
                                Guests can type the code on the home page or scan the QR code.
                            </p>
                        </div>
                        <div>
                            <!-- eslint-disable-next-line vue/no-v-html -->
                            <div class="mp-qr" data-test="done-qr" v-html="createdParty.qr"></div>
                            <div class="text-secondary small text-center mt-2">Scan to join</div>
                        </div>
                    </div>
                </StageSurface>
                <div class="card-footer d-flex flex-wrap gap-2">
                    <Link :href="dashboard(createdParty.code).url" class="btn btn-primary">
                        <i class="icon ti ti-layout-dashboard" aria-hidden="true"></i>Open Dashboard
                    </Link>
                    <a :href="tv(createdParty.code).url" class="btn btn-outline-secondary">
                        <i class="icon ti ti-device-tv" aria-hidden="true"></i>Open TV
                    </a>
                    <Link :href="edit(createdParty.code).url" class="btn btn-link link-secondary ms-auto">
                        Party settings
                    </Link>
                </div>
            </div>

            <form v-else class="card" novalidate @submit.prevent="onSubmit">
                <div class="card-body">
                    <template v-if="step === 1">
                        <h2 class="mp-step-title">Name your party</h2>
                        <p class="mp-sub">Guests will see this name.</p>
                        <SettingRow
                            label="Name"
                            for="name"
                            hint="Guests see this on the TV and in their party list."
                            :error="nameError || err('name')"
                        >
                            <input
                                id="name"
                                v-model="form.name"
                                type="text"
                                name="name"
                                class="form-control"
                                :class="{'is-invalid': nameError || err('name')}"
                                :aria-invalid="Boolean(nameError || err('name'))"
                                placeholder="Coyote LAN 26"
                                autofocus
                                @input="nameError = ''"
                            />
                        </SettingRow>
                        <SettingRow
                            label="Music provider"
                            for="provider_id"
                            hint="Cannot be changed after creation"
                            :error="err('provider_id')"
                        >
                            <select
                                id="provider_id"
                                v-model="form.provider_id"
                                name="provider_id"
                                class="form-select"
                                :class="{'is-invalid': err('provider_id')}"
                            >
                                <option v-for="id in providerIds" :key="id" :value="id">{{ ucfirst(id) }}</option>
                            </select>
                        </SettingRow>
                    </template>

                    <template v-else-if="step === 2">
                        <h2 class="mp-step-title">Pick the music</h2>
                        <p class="mp-sub">What plays when nobody has requested anything.</p>
                        <SettingRow
                            label="Fallback Playlist"
                            for="backup_playlist_id"
                            hint="Plays when the Queue is empty. Pre-selected from your library; change it any time."
                            stacked
                            :error="err('backup_playlist_id') ?? err('custom_backup_playlist_id')"
                        >
                            <PlaylistPicker
                                id="backup_playlist_id"
                                v-model="form.backup_playlist_id"
                                v-model:custom="form.custom_backup_playlist_id"
                                :playlists="playlists"
                                :host-name="hostName"
                                :provider-name="ucfirst(form.provider_id)"
                                :invalid="Boolean(err('backup_playlist_id') ?? err('custom_backup_playlist_id'))"
                            />
                        </SettingRow>
                        <SettingRow
                            label="Play on"
                            for="device_id"
                            hint="The device Music Party starts playback on. You can switch from the Dashboard."
                            :error="err('device_id')"
                        >
                            <select
                                id="device_id"
                                v-model="form.device_id"
                                name="device_id"
                                class="form-select"
                                :class="{'is-invalid': err('device_id')}"
                            >
                                <option value="">None</option>
                                <option v-for="device in devices" :key="device.id" :value="device.id">
                                    {{ device.name }}
                                </option>
                            </select>
                        </SettingRow>
                    </template>

                    <template v-else>
                        <h2 class="mp-step-title">Set the defaults</h2>
                        <p class="mp-sub">The same settings as Settings. Leave them alone if you are not sure.</p>
                        <h3 class="mp-sec">Requests</h3>
                        <SettingRow
                            v-for="item in switches"
                            :key="item.name"
                            :label="item.label"
                            :for="item.name"
                            :hint="item.hint"
                            :error="err(item.name)"
                        >
                            <span class="form-check form-switch mb-0 ps-0 min-height-0">
                                <input
                                    :id="item.name"
                                    v-model="form[item.name]"
                                    type="checkbox"
                                    role="switch"
                                    class="form-check-input m-0"
                                    :name="item.name"
                                    value="1"
                                />
                            </span>
                        </SettingRow>
                        <SettingRow
                            label="Requests per person"
                            for="max_requests"
                            hint="How many requests one member can have waiting"
                            :error="err('max_requests')"
                        >
                            <input
                                id="max_requests"
                                v-model="form.max_requests"
                                type="number"
                                min="0"
                                name="max_requests"
                                class="form-control"
                                :class="{'is-invalid': err('max_requests')}"
                            />
                        </SettingRow>
                        <h3 class="mp-sec">Screens and region</h3>
                        <SettingRow
                            label="Show join QR code"
                            for="show_qrcode"
                            hint="Shown on the TV screen so guests can join"
                        >
                            <span class="form-check form-switch mb-0 ps-0 min-height-0">
                                <input
                                    id="show_qrcode"
                                    v-model="form.show_qrcode"
                                    type="checkbox"
                                    role="switch"
                                    class="form-check-input m-0"
                                    name="show_qrcode"
                                    value="1"
                                />
                            </span>
                        </SettingRow>
                        <SettingRow
                            label="Country"
                            for="country"
                            hint="Used for search and browse results"
                            :error="err('country')"
                        >
                            <OptionPicker
                                id="country"
                                v-model="form.country"
                                label="Country"
                                :options="countryOptions"
                                :invalid="Boolean(err('country'))"
                            />
                        </SettingRow>
                        <SettingRow
                            label="Timezone"
                            for="timezone"
                            hint="Used by time-based mods and Scheduled Requests. Blank uses the instance timezone"
                            :error="err('timezone')"
                        >
                            <OptionPicker
                                id="timezone"
                                v-model="form.timezone"
                                label="Timezone"
                                :options="timezoneOptions"
                                :invalid="Boolean(err('timezone'))"
                            />
                        </SettingRow>
                    </template>
                </div>
                <div class="card-footer">
                    <button
                        v-if="step > 1"
                        type="button"
                        class="btn btn-link link-secondary px-0"
                        data-test="back"
                        @click="step -= 1"
                    >
                        <i class="icon ti ti-arrow-left" aria-hidden="true"></i>Back
                    </button>
                    <button
                        v-if="step < 3"
                        type="button"
                        class="btn btn-outline-secondary"
                        :class="step === 1 ? '' : 'ms-auto'"
                        :disabled="form.processing"
                        data-test="create-defaults"
                        @click="submit"
                    >
                        <i v-if="step === 1" class="icon ti ti-bolt" aria-hidden="true"></i>Create with defaults
                    </button>
                    <button
                        type="submit"
                        class="btn btn-primary"
                        :class="step === 1 ? 'ms-auto' : step === 3 ? 'ms-auto' : ''"
                        :disabled="form.processing"
                        data-test="next"
                    >
                        <template v-if="step < 3">
                            Next: {{ STEPS[step] }}<i class="icon ti ti-arrow-right ms-1" aria-hidden="true"></i>
                        </template>
                        <template v-else><i class="icon ti ti-check" aria-hidden="true"></i>Create party</template>
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
