<script setup lang="ts">
import VoteControl from '@/components/VoteControl.vue';
import {formatMs} from '@/lib/dashboard';
import {legacyAccentStyle, legacyFallbackName, legacyRowClasses} from '@/lib/decoration';
import {show as showRequest} from '@/routes/parties/songs';
import type {LegacyArtist, LegacyUpcomingSong} from '@/types';

const props = defineProps<{
    song: LegacyUpcomingSong;
    position: number;
    party: string;
    timezone: string;
    canManage: boolean;
    canDownvote: boolean;
    isMine: boolean;
    failed: boolean;
    menuOpen: boolean;
}>();

const emit = defineEmits<{
    vote: [value: number];
    schedule: [event?: MouseEvent];
    remove: [];
    toggleMenu: [id: number];
}>();

const name = legacyFallbackName(props.song);
const customFallbackName =
    name === null || name === 'Fallback Track' || name === 'From Fallback Playlist' ? null : name;

function combineArtists(artists: LegacyArtist[] | undefined): string {
    return (artists ?? []).map((artist) => artist.name).join(', ');
}

function formatPlaysAt(iso: string): string {
    const day = (value: Date): string => new Intl.DateTimeFormat('en-CA', {timeZone: props.timezone}).format(value);
    const date = new Date(iso);
    const time = new Intl.DateTimeFormat(undefined, {timeStyle: 'short', timeZone: props.timezone}).format(date);
    if (day(date) === day(new Date())) {
        return time;
    }

    return `${new Intl.DateTimeFormat(undefined, {month: 'short', day: 'numeric', timeZone: props.timezone}).format(date)}, ${time}`;
}
</script>

<template>
    <div
        class="list-group-item mp-qrow"
        :class="[
            {'is-mine': isMine, 'mp-failed': failed},
            ...legacyRowClasses(song)
                .split(' ')
                .filter((c) => !['row', 'g-2', 'align-items-center'].includes(c)),
        ]"
        :style="legacyAccentStyle(song)"
        data-test="track-row"
    >
        <div class="q-pos" aria-hidden="true">{{ position }}</div>
        <div class="q-art">
            <img
                :src="song.album.image_url"
                :title="song.album.name"
                :alt="song.album.name"
                class="rounded"
                width="48"
                height="48"
                loading="lazy"
                decoding="async"
            />
        </div>
        <div class="q-main">
            <div class="q-title">
                <span>{{ song.name }}</span>
                <span v-if="song.next" class="badge bg-green-lt" data-test="next-request">Next</span>
                <span v-if="song.held" class="badge bg-yellow-lt" data-test="held-request">Held</span>
                <span v-if="isMine" class="badge bg-blue-lt" data-test="your-request">You</span>
                <span v-if="song.decoration?.label" class="badge bg-azure-lt" data-test="decoration-label">{{
                    song.decoration.label
                }}</span>
                <span
                    v-if="song.scheduled_for"
                    class="plays-at"
                    data-test="scheduled-time"
                    :title="`Plays at ${formatPlaysAt(song.scheduled_for)}`"
                    ><i class="icon ti ti-calendar-time"></i>Plays at {{ formatPlaysAt(song.scheduled_for) }}</span
                >
            </div>
            <div class="q-by">
                {{ combineArtists(song.artists) }} &middot;
                <template v-if="isMine">Requested by <b>you</b></template>
                <template v-else-if="song.user"
                    >Requested by <b>{{ song.user }}</b></template
                >
                <template v-else-if="customFallbackName">{{ customFallbackName }}</template>
                <span v-else class="mp-credit" data-test="fallback-credit">
                    <i class="icon ti ti-playlist"></i>From the party&rsquo;s Fallback Playlist
                </span>
                <span class="q-length-inline"> &middot; {{ formatMs(song.length) }}</span>
            </div>
        </div>
        <div class="q-len text-secondary">{{ formatMs(song.length) }}</div>
        <div class="q-votes">
            <VoteControl
                :score="song.score"
                :vote="song.vote"
                :can-downvote="canDownvote"
                :label="song.name"
                @vote="(value) => emit('vote', value)"
            />
        </div>
        <div v-if="canManage" class="q-manage">
            <button
                type="button"
                class="btn btn-icon btn-ghost-secondary sched"
                :class="{'is-set': song.scheduled_for}"
                :aria-label="`${song.scheduled_for ? 'Change schedule for' : 'Schedule'} ${song.name}`"
                :title="song.scheduled_for ? 'Change schedule' : 'Schedule'"
                data-test="schedule"
                @click="emit('schedule', $event)"
            >
                <i class="icon ti ti-calendar-clock"></i>
            </button>
            <button
                type="button"
                class="btn btn-icon btn-ghost-secondary rm"
                :aria-label="`Remove ${song.name}`"
                title="Remove from Queue"
                data-test="remove"
                @click="emit('remove')"
            >
                <i class="icon ti ti-trash"></i>
            </button>
        </div>
        <div v-if="canManage" class="q-menu" @click.stop>
            <button
                type="button"
                class="btn btn-icon btn-ghost-secondary"
                :aria-label="`Actions for ${song.name}`"
                aria-haspopup="true"
                :aria-expanded="menuOpen"
                data-test="actions"
                @click="emit('toggleMenu', song.id)"
            >
                <i class="icon ti ti-dots-vertical"></i>
            </button>
            <div v-if="menuOpen" class="dropdown-menu show" data-test="actions-menu">
                <button type="button" class="dropdown-item" data-test="schedule-menu" @click="emit('schedule')">
                    <i class="icon ti ti-calendar-clock me-2"></i>
                    <span class="q-menu-text">
                        <span>{{ song.scheduled_for ? 'Change schedule' : 'Schedule' }}</span>
                        <small class="text-secondary">Pick a day and time for it to play</small>
                    </span>
                </button>
                <a :href="showRequest({party, song: song.id}).url" class="dropdown-item" data-test="view-request">
                    <i class="icon ti ti-external-link me-2"></i>
                    <span class="q-menu-text">
                        <span>View Request</span>
                        <small class="text-secondary">Open its detail page</small>
                    </span>
                </a>
                <button type="button" class="dropdown-item text-danger" data-test="remove-menu" @click="emit('remove')">
                    <i class="icon ti ti-trash me-2"></i>
                    <span class="q-menu-text">
                        <span>Remove from Queue</span>
                        <small class="text-secondary">Guests can request it again</small>
                    </span>
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.mp-qrow {
    display: grid;
    grid-template-columns: 2ch 48px minmax(0, 1fr) auto auto auto;
    grid-template-areas: 'pos art main len votes manage';
    align-items: center;
    gap: 8px 14px;
    padding: 10px 12px;
}

.q-pos {
    grid-area: pos;
    text-align: end;
    color: var(--tblr-secondary-color);
    font-size: 0.8125rem;
    font-variant-numeric: tabular-nums;
}

.q-art {
    grid-area: art;
}

.q-art img {
    inline-size: 48px;
    block-size: 48px;
    object-fit: cover;
    border-radius: 8px;
}

.q-main {
    grid-area: main;
    min-width: 0;
    overflow-wrap: anywhere;
}

.q-title {
    font-weight: 600;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
}

.q-by {
    font-size: 0.875rem;
    color: var(--tblr-secondary-color);
}

.q-by b {
    color: var(--tblr-body-color);
    font-weight: 600;
}

.mp-credit {
    display: inline-flex;
    align-items: center;
    gap: 0.35em;
}

.q-length-inline {
    display: none;
}

.q-len {
    grid-area: len;
    font-size: 0.8125rem;
    font-variant-numeric: tabular-nums;
}

.q-votes {
    grid-area: votes;
}

.q-votes :deep(.vote-control) {
    background: var(--tblr-bg-surface-secondary);
    border-radius: 99px;
    padding: 2px 4px;
}

.q-votes :deep(.vote-button) {
    min-width: 44px;
    min-height: 44px;
}

.q-votes :deep(.vote-button .icon) {
    font-size: 1.375rem;
}

.q-votes :deep(.vote-score) {
    font-weight: 600;
    min-width: 2ch;
}

.q-manage {
    grid-area: manage;
    align-self: stretch;
    display: flex;
    align-items: center;
    margin-inline-start: 4px;
    padding-inline-start: 16px;
    border-inline-start: 1px solid var(--tblr-border-color);
}

.plays-at {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 1px 8px;
    border-radius: 99px;
    font-size: 0.75rem;
    font-weight: 600;
    background: color-mix(in oklab, var(--tblr-yellow) 22%, var(--tblr-bg-surface));
    color: color-mix(in oklab, var(--tblr-yellow) 45%, var(--tblr-body-color));
}

.q-manage .btn.sched.is-set {
    color: var(--tblr-yellow);
}

.q-manage .btn,
.q-menu .btn {
    min-width: 44px;
    min-height: 44px;
}

.q-manage .btn .icon {
    font-size: 1.25rem;
}

.q-manage .btn.rm:hover {
    color: var(--tblr-danger);
    background: color-mix(in oklab, var(--tblr-danger) 10%, transparent);
}

.q-menu {
    grid-area: menu;
    display: none;
    position: relative;
    align-self: start;
}

.q-menu .dropdown-menu {
    inset: 100% 0 auto auto;
    min-width: 15rem;
    z-index: 5;
}

.q-menu .dropdown-item {
    display: flex;
    align-items: flex-start;
    min-height: 44px;
}

.q-menu-text {
    display: flex;
    flex-direction: column;
}

.mp-qrow.mp-failed {
    box-shadow: inset 4px 0 0 var(--tblr-danger);
}

@media (max-width: 575px) {
    .mp-qrow {
        grid-template-columns: 48px minmax(0, 1fr) auto;
        grid-template-areas:
            'art main menu'
            'art votes votes';
        gap: 4px 12px;
        padding: 12px 10px;
    }

    .q-pos,
    .q-len,
    .q-manage {
        display: none;
    }

    .q-menu {
        display: block;
    }

    .q-length-inline {
        display: inline;
    }

    .q-votes {
        justify-self: start;
        margin-inline-start: -4px;
    }

    .q-votes :deep(.vote-control) {
        inline-size: fit-content;
        justify-content: flex-start;
    }
}
</style>
