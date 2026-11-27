<script setup lang="ts">
import {computed, ref} from 'vue';
import CopyButton from '@/components/CopyButton.vue';
import SettingRow from '@/components/SettingRow.vue';
import type {NamedOption, PartyFormState} from '@/lib/partyForm';

const props = defineProps<{
    tab: 'general' | 'requests' | 'playback' | 'screens';
    form: PartyFormState;
    playlists: NamedOption[];
    devices: NamedOption[];
    providerName: string;
    instanceTimezone: string;
    timezones: string[];
    contextPlaylistName?: string | null;
    encore: {host: string; key: string | null; channel: string; secret: string | null};
}>();

const relayUrl = computed(() => `wss://${props.encore.host}/app/${props.encore.key}`);
const advancedOpen = ref(props.form.context_playlist_id !== '');

const relayRows = computed(() => [
    {label: 'Relay URL', value: relayUrl.value, copy: 'Copy relay URL'},
    {label: 'Channel', value: props.encore.channel, copy: 'Copy channel'},
    {label: 'App key', value: props.encore.key ?? '', copy: 'Copy app key'},
    ...(props.encore.secret ? [{label: 'App secret', value: props.encore.secret, copy: 'Copy app secret'}] : []),
]);

function err(field: string): string | undefined {
    return (props.form.errors as Record<string, string | undefined>)[field];
}
</script>

<template>
    <div>
        <section v-if="tab === 'general'">
            <h3 class="settings-heading">Party</h3>
            <SettingRow
                label="Party is running"
                for="active"
                hint="Turn off to pause the Queue. Requests wait until it's back on."
            >
                <span class="form-check form-switch mb-0 ps-0 min-height-0">
                    <input
                        id="active"
                        v-model="form.active"
                        type="checkbox"
                        role="switch"
                        class="form-check-input m-0"
                        name="active"
                        value="1"
                    />
                </span>
            </SettingRow>
            <SettingRow label="Name" for="name" :error="err('name')">
                <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    name="name"
                    class="form-control"
                    :class="{'is-invalid': err('name')}"
                    placeholder="Name"
                />
            </SettingRow>
            <SettingRow label="Music provider" hint="Set when the party was created">
                <span class="text-secondary">{{ providerName }}</span>
            </SettingRow>
            <SettingRow
                label="Country"
                for="country"
                hint="Two-letter code used for search and browse results"
                :error="err('country')"
            >
                <input
                    id="country"
                    v-model="form.country"
                    type="text"
                    name="country"
                    maxlength="2"
                    class="form-control"
                    :class="{'is-invalid': err('country')}"
                    placeholder="GB"
                />
            </SettingRow>
            <SettingRow
                label="Timezone"
                for="timezone"
                hint="Used by time-based mods and scheduled requests. Blank uses the instance timezone"
                :error="err('timezone')"
            >
                <input
                    id="timezone"
                    v-model="form.timezone"
                    type="text"
                    name="timezone"
                    list="timezone-options"
                    class="form-control"
                    :class="{'is-invalid': err('timezone')}"
                    :placeholder="instanceTimezone"
                />
                <datalist id="timezone-options">
                    <option v-for="identifier in timezones" :key="identifier" :value="identifier"></option>
                </datalist>
            </SettingRow>
        </section>

        <section v-if="tab === 'requests'">
            <h3 class="settings-heading">Requests</h3>
            <SettingRow label="Accept requests" for="allow_requests">
                <span class="form-check form-switch mb-0 ps-0 min-height-0">
                    <input
                        id="allow_requests"
                        v-model="form.allow_requests"
                        type="checkbox"
                        role="switch"
                        class="form-check-input m-0"
                        name="allow_requests"
                        value="1"
                    />
                </span>
            </SettingRow>
            <SettingRow label="Allow downvotes" for="downvotes">
                <span class="form-check form-switch mb-0 ps-0 min-height-0">
                    <input
                        id="downvotes"
                        v-model="form.downvotes"
                        type="checkbox"
                        role="switch"
                        class="form-check-input m-0"
                        name="downvotes"
                        value="1"
                    />
                </span>
            </SettingRow>
            <SettingRow
                label="Raffle mode"
                for="weighted"
                hint="Pick the next track at random, weighted by score, instead of the top-scored one"
            >
                <span class="form-check form-switch mb-0 ps-0 min-height-0">
                    <input
                        id="weighted"
                        v-model="form.weighted"
                        type="checkbox"
                        role="switch"
                        class="form-check-input m-0"
                        name="weighted"
                        value="1"
                    />
                </span>
            </SettingRow>
            <SettingRow label="Allow explicit tracks" for="explicit">
                <span class="form-check form-switch mb-0 ps-0 min-height-0">
                    <input
                        id="explicit"
                        v-model="form.explicit"
                        type="checkbox"
                        role="switch"
                        class="form-check-input m-0"
                        name="explicit"
                        value="1"
                    />
                </span>
            </SettingRow>
            <SettingRow
                label="Hold Flagged Requests for approval"
                for="flag_requests"
                hint="They wait in Moderation until a manager or moderator decides"
            >
                <span class="form-check form-switch mb-0 ps-0 min-height-0">
                    <input
                        id="flag_requests"
                        v-model="form.flag_requests"
                        type="checkbox"
                        role="switch"
                        class="form-check-input m-0"
                        name="flag_requests"
                        value="1"
                    />
                </span>
            </SettingRow>

            <h3 class="settings-heading settings-heading-spaced">Limits</h3>
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
            <SettingRow
                label="Downvotes per person"
                for="downvotes_per_hour"
                hint="Per hour"
                :error="err('downvotes_per_hour')"
            >
                <input
                    id="downvotes_per_hour"
                    v-model="form.downvotes_per_hour"
                    type="number"
                    min="0"
                    name="downvotes_per_hour"
                    class="form-control"
                    :class="{'is-invalid': err('downvotes_per_hour')}"
                />
            </SettingRow>
            <SettingRow
                label="Track length"
                for="min_song_length"
                hint="Shortest and longest track a member can request, in seconds"
                :error="err('min_song_length') ?? err('max_song_length')"
            >
                <div class="input-group">
                    <input
                        id="min_song_length"
                        v-model="form.min_song_length"
                        type="number"
                        min="0"
                        name="min_song_length"
                        class="form-control"
                        aria-label="Minimum length in seconds"
                        :class="{'is-invalid': err('min_song_length')}"
                    />
                    <span class="input-group-text">to</span>
                    <input
                        id="max_song_length"
                        v-model="form.max_song_length"
                        type="number"
                        min="0"
                        name="max_song_length"
                        class="form-control"
                        aria-label="Maximum length in seconds"
                        :class="{'is-invalid': err('max_song_length')}"
                    />
                </div>
            </SettingRow>
            <SettingRow
                label="Repeat cooldown"
                for="no_repeat_interval"
                hint="Seconds before the same track can be requested again"
                :error="err('no_repeat_interval')"
            >
                <input
                    id="no_repeat_interval"
                    v-model="form.no_repeat_interval"
                    type="number"
                    min="0"
                    name="no_repeat_interval"
                    class="form-control"
                    :class="{'is-invalid': err('no_repeat_interval')}"
                />
            </SettingRow>
        </section>

        <section v-if="tab === 'playback'">
            <h3 class="settings-heading">Playback</h3>
            <SettingRow
                label="Fallback Playlist"
                for="backup_playlist_id"
                hint="Plays when the Queue is empty"
                :error="err('backup_playlist_id') ?? err('custom_backup_playlist_id')"
            >
                <select
                    id="backup_playlist_id"
                    v-model="form.backup_playlist_id"
                    name="backup_playlist_id"
                    class="form-select"
                    :class="{'is-invalid': err('backup_playlist_id')}"
                >
                    <option v-for="playlist in playlists" :key="playlist.id" :value="playlist.id">
                        {{ playlist.name }}
                    </option>
                    <option value="other">Other playlist</option>
                </select>
                <input
                    v-if="form.backup_playlist_id === 'other'"
                    id="custom_backup_playlist_id"
                    v-model="form.custom_backup_playlist_id"
                    type="text"
                    name="custom_backup_playlist_id"
                    class="form-control"
                    :class="{'is-invalid': err('custom_backup_playlist_id')}"
                    placeholder="Spotify playlist ID"
                />
            </SettingRow>
            <SettingRow
                label="Save played tracks to"
                for="history_playlist_id"
                hint="Adds every track sent to the player to this playlist"
                :error="err('history_playlist_id')"
            >
                <select
                    id="history_playlist_id"
                    v-model="form.history_playlist_id"
                    name="history_playlist_id"
                    class="form-select"
                    :class="{'is-invalid': err('history_playlist_id')}"
                >
                    <option value="none">None</option>
                    <option v-for="playlist in playlists" :key="playlist.id" :value="playlist.id">
                        {{ playlist.name }}
                    </option>
                </select>
            </SettingRow>
            <SettingRow
                label="Always play on"
                for="device_id"
                hint="Keeps playback on this device even if another takes over"
                :error="err('device_id')"
            >
                <select
                    id="device_id"
                    v-model="form.device_id"
                    name="device_id"
                    class="form-select flex-grow-1 w-auto"
                    :disabled="!form.force"
                    :class="{'is-invalid': err('device_id')}"
                >
                    <option value="">None</option>
                    <option v-for="device in devices" :key="device.id" :value="device.id">{{ device.name }}</option>
                </select>
                <span class="form-check form-switch mb-0 ps-0 min-height-0">
                    <input
                        id="force"
                        v-model="form.force"
                        type="checkbox"
                        role="switch"
                        class="form-check-input m-0"
                        name="force"
                        value="1"
                        aria-label="Always play on the selected device"
                    />
                </span>
            </SettingRow>
            <SettingRow
                label="Ask Spotify what's playing"
                for="poll"
                hint="For speakers that don't report playback, such as a console or smart speaker"
            >
                <span class="form-check form-switch mb-0 ps-0 min-height-0">
                    <input
                        id="poll"
                        v-model="form.poll"
                        type="checkbox"
                        role="switch"
                        class="form-check-input m-0"
                        name="poll"
                        value="1"
                    />
                </span>
            </SettingRow>
            <div class="setting-advanced">
                <button
                    type="button"
                    class="btn btn-link px-0"
                    :aria-expanded="advancedOpen"
                    aria-controls="advanced-playback"
                    @click="advancedOpen = !advancedOpen"
                >
                    {{ advancedOpen ? 'Hide' : 'Show' }} advanced
                </button>
                <div v-show="advancedOpen" id="advanced-playback">
                    <SettingRow
                        label="Context playlist"
                        for="context_playlist_id"
                        :hint="`${contextPlaylistName ? `In use: ${contextPlaylistName}. ` : ''}Playlist ID on ${providerName}'s account. Leave blank to let Music Party manage it. The playlist is kept empty.`"
                        :error="err('context_playlist_id')"
                    >
                        <input
                            id="context_playlist_id"
                            v-model="form.context_playlist_id"
                            type="text"
                            name="context_playlist_id"
                            class="form-control"
                            :class="{'is-invalid': err('context_playlist_id')}"
                            placeholder="Automatic"
                        />
                    </SettingRow>
                </div>
            </div>
        </section>

        <section v-if="tab === 'screens'">
            <h3 class="settings-heading">TV screen</h3>
            <SettingRow label="Show join QR code" for="show_qrcode" hint="Shown on the TV screen so guests can join">
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
            <h3 id="encore-relay" class="settings-heading settings-heading-spaced">Encore relay</h3>
            <SettingRow v-for="row in relayRows" :key="row.label" :label="row.label" wide>
                <code class="text-break">{{ row.value }}</code>
                <CopyButton :value="row.value" :label="row.copy" />
            </SettingRow>
        </section>
    </div>
</template>

<style scoped>
.settings-heading {
    margin: 0;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--tblr-border-color);
    color: var(--tblr-secondary);
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.settings-heading-spaced {
    margin-top: 2rem;
}

.setting-advanced {
    padding-top: 0.25rem;
}
</style>
