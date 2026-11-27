<script setup lang="ts">
import {Link} from '@inertiajs/vue3';
import type {LimitField, NamedOption, PartyFormState, SwitchField} from '@/lib/partyForm';

defineProps<{
    form: PartyFormState;
    playlists: NamedOption[];
    devices: NamedOption[];
    instanceTimezone: string;
    timezones: string[];
    cancelUrl: string;
    providerIds?: string[];
    providerName?: string;
    showContextPlaylist?: boolean;
    contextPlaylistName?: string | null;
}>();

defineEmits<{submit: []}>();

const switches: {name: SwitchField; label: string}[] = [
    {name: 'allow_requests', label: 'Accept requests'},
    {name: 'downvotes', label: 'Allow downvotes'},
    {name: 'show_qrcode', label: 'Show join QR code'},
    {name: 'flag_requests', label: 'Hold Flagged Requests for approval'},
];

const limits: {name: LimitField; label: string; hint: string; unit?: string}[] = [
    {
        name: 'min_song_length',
        label: 'Minimum Song Length',
        hint: 'The minimum length a requested song can be',
        unit: 'seconds',
    },
    {
        name: 'max_song_length',
        label: 'Maximum Song Length',
        hint: 'The maximum length a requested song can be',
        unit: 'seconds',
    },
    {
        name: 'no_repeat_interval',
        label: 'Song Cooldown',
        hint: 'The amount of time after a song is queued before it can be requested again',
        unit: 'seconds',
    },
    {
        name: 'downvotes_per_hour',
        label: 'Max Downvotes',
        hint: 'The maximum number of times a user can downvote in a 1 hour period',
        unit: 'per hour',
    },
    {
        name: 'max_requests',
        label: 'Max Requests',
        hint: 'The maximum number of requests that a user can have in the queue',
    },
];

const ucfirst = (value: string): string => value.charAt(0).toUpperCase() + value.slice(1);
</script>

<template>
    <form class="card" @submit.prevent="$emit('submit')">
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label required">Music Provider</label>
                <div>
                    <template v-if="providerName">
                        <input type="text" class="form-control" :value="providerName" disabled />
                        <small class="form-hint">The Music Provider cannot be changed after creation</small>
                    </template>
                    <template v-else>
                        <select
                            v-model="form.provider_id"
                            name="provider_id"
                            class="form-control"
                            :class="{'is-invalid': form.errors.provider_id}"
                        >
                            <option v-for="id in providerIds" :key="id" :value="id">{{ ucfirst(id) }}</option>
                        </select>
                        <p v-if="form.errors.provider_id" class="invalid-feedback">{{ form.errors.provider_id }}</p>
                    </template>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label required">Name</label>
                <div>
                    <input
                        v-model="form.name"
                        type="text"
                        name="name"
                        class="form-control"
                        :class="{'is-invalid': form.errors.name}"
                        placeholder="Name"
                    />
                    <small class="form-hint">Name for the party</small>
                    <p v-if="form.errors.name" class="invalid-feedback">{{ form.errors.name }}</p>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label required">Country</label>
                <div>
                    <input
                        v-model="form.country"
                        type="text"
                        name="country"
                        maxlength="2"
                        class="form-control"
                        :class="{'is-invalid': form.errors.country}"
                        placeholder="GB"
                    />
                    <small class="form-hint">Two-letter country code used for search and browse results</small>
                    <p v-if="form.errors.country" class="invalid-feedback">{{ form.errors.country }}</p>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Timezone</label>
                <div>
                    <input
                        v-model="form.timezone"
                        type="text"
                        name="timezone"
                        list="timezone-options"
                        class="form-control"
                        :class="{'is-invalid': form.errors.timezone}"
                        :placeholder="instanceTimezone"
                    />
                    <datalist id="timezone-options">
                        <option v-for="identifier in timezones" :key="identifier" :value="identifier"></option>
                    </datalist>
                    <small class="form-hint">
                        Used by time-based mods and scheduled requests. Leave blank to use the instance timezone
                    </small>
                    <p v-if="form.errors.timezone" class="invalid-feedback">{{ form.errors.timezone }}</p>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label required">Fallback Playlist</label>
                <div>
                    <select
                        id="backup_playlist_id"
                        v-model="form.backup_playlist_id"
                        name="backup_playlist_id"
                        class="form-control"
                        :class="{'is-invalid': form.errors.backup_playlist_id}"
                    >
                        <option v-for="playlist in playlists" :key="playlist.id" :value="playlist.id">
                            {{ playlist.name }}
                        </option>
                        <option value="other">Other playlist</option>
                    </select>
                    <input
                        id="custom_backup_playlist_id"
                        v-model="form.custom_backup_playlist_id"
                        type="text"
                        name="custom_backup_playlist_id"
                        class="form-control mt-2"
                        :class="{
                            'is-invalid': form.errors.custom_backup_playlist_id,
                            'd-none': form.backup_playlist_id !== 'other',
                        }"
                        placeholder="Spotify Playlist ID"
                    />
                    <small class="form-hint">Plays when the Queue is empty</small>
                    <p v-if="form.errors.backup_playlist_id" class="invalid-feedback">
                        {{ form.errors.backup_playlist_id }}
                    </p>
                    <p v-if="form.errors.custom_backup_playlist_id" class="invalid-feedback">
                        {{ form.errors.custom_backup_playlist_id }}
                    </p>
                </div>
            </div>
            <div class="mb-3">
                <label v-for="item in switches" :key="item.name" class="form-check form-switch">
                    <input
                        :id="item.name"
                        v-model="form[item.name]"
                        type="checkbox"
                        class="form-check-input"
                        :name="item.name"
                        value="1"
                    />
                    {{ item.label }}
                </label>

                <h2 class="mt-4">Restrictions</h2>

                <label class="form-check form-switch">
                    <input v-model="form.explicit" type="checkbox" class="form-check-input" name="explicit" value="1" />
                    Allow explicit songs
                </label>

                <div class="mb-3 row">
                    <div v-for="limit in limits" :key="limit.name" class="mb-3 col-md-6">
                        <label class="form-label">{{ limit.label }}</label>
                        <div class="input-group">
                            <input
                                v-model="form[limit.name]"
                                type="text"
                                :name="limit.name"
                                class="form-control"
                                :class="{'is-invalid': form.errors[limit.name]}"
                            />
                            <span v-if="limit.unit" class="input-group-text">{{ limit.unit }}</span>
                        </div>
                        <small class="form-hint">{{ limit.hint }}</small>
                        <p v-if="form.errors[limit.name]" class="invalid-feedback">{{ form.errors[limit.name] }}</p>
                    </div>
                </div>

                <h2 class="mt-4">Advanced Options</h2>

                <p>You might need these options if you're playing the Party on something that isn't a computer.</p>

                <label class="form-check form-switch">
                    <input v-model="form.poll" type="checkbox" class="form-check-input" name="poll" value="1" />
                    Poll the Spotify API for updates
                </label>
                <label class="form-check form-switch">
                    <input v-model="form.force" type="checkbox" class="form-check-input" name="force" value="1" />
                    Force playback on the selected device
                </label>
                <div v-if="showContextPlaylist" class="mb-3 mt-3">
                    <label class="form-label" for="context_playlist_id">Context playlist</label>
                    <input
                        id="context_playlist_id"
                        v-model="form.context_playlist_id"
                        type="text"
                        name="context_playlist_id"
                        class="form-control"
                        :class="{'is-invalid': form.errors.context_playlist_id}"
                        placeholder="Music Party Context (automatic)"
                    />
                    <small class="form-hint">
                        <template v-if="contextPlaylistName">In use: {{ contextPlaylistName }}. </template>
                        Playlist ID on {{ providerName ?? 'the host' }}'s account. Leave blank to let Music Party manage
                        it. The playlist is kept empty.
                    </small>
                    <p v-if="form.errors.context_playlist_id" class="invalid-feedback">
                        {{ form.errors.context_playlist_id }}
                    </p>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Playback Device</label>
                <div>
                    <select
                        id="device_id"
                        v-model="form.device_id"
                        name="device_id"
                        class="form-control"
                        :class="{'is-invalid': form.errors.device_id}"
                    >
                        <option value="">None</option>
                        <option v-for="device in devices" :key="device.id" :value="device.id">{{ device.name }}</option>
                    </select>
                    <small class="form-hint">The device to play the party on, if force is enabled</small>
                    <p v-if="form.errors.device_id" class="invalid-feedback">{{ form.errors.device_id }}</p>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">History Playlist</label>
                <div>
                    <select
                        id="history_playlist_id"
                        v-model="form.history_playlist_id"
                        name="history_playlist_id"
                        class="form-control"
                        :class="{'is-invalid': form.errors.history_playlist_id}"
                    >
                        <option value="none">None</option>
                        <option v-for="playlist in playlists" :key="playlist.id" :value="playlist.id">
                            {{ playlist.name }}
                        </option>
                    </select>
                    <small class="form-hint">
                        If you set a history playlist, anything added to the queue will be added to this playlist.
                    </small>
                    <p v-if="form.errors.history_playlist_id" class="invalid-feedback">
                        {{ form.errors.history_playlist_id }}
                    </p>
                </div>
            </div>
        </div>
        <div class="card-footer text-end">
            <div class="d-flex">
                <Link :href="cancelUrl" class="btn btn-link">Cancel</Link>
                <button type="submit" class="btn btn-primary ms-auto" :disabled="form.processing">Save</button>
            </div>
        </div>
    </form>
</template>
