<script setup>
import {router} from '@inertiajs/vue3';
import {ref} from 'vue';
import Icon from './Icon.vue';
import TrackLink from './TrackLink.vue';
import Decorations from './Decorations.vue';
import ModSlot from './ModSlot.vue';
import TrackThumb from './TrackThumb.vue';
import {formatDuration, requesterLabel} from '../lib/format';

const props = defineProps({
    queue: {type: Array, default: () => []},
    partyCode: {type: String, default: ''},
    downvotesEnabled: {type: Boolean, default: true},
    readOnly: {type: Boolean, default: false},
});

const pending = ref(null);
const errors = ref({});

const isLocked = (item) => props.readOnly || item.status === 'up_next';

const vote = (item, direction) => {
    if (isLocked(item) || pending.value === item.id) {
        return;
    }
    const value = direction === 'up' ? 1 : -1;
    const url = `/parties/${props.partyCode}/requests/${item.id}/vote`;
    const options = {
        preserveScroll: true,
        preserveState: true,
        onStart: () => {
            pending.value = item.id;
            errors.value = {...errors.value, [item.id]: null};
        },
        onError: (e) => {
            errors.value = {...errors.value, [item.id]: e.vote ?? e.value ?? 'Could not record your vote.'};
        },
        onFinish: () => {
            pending.value = null;
        },
    };

    if (item.my_vote === value) {
        router.delete(url, options);
    } else {
        router.put(url, {value: direction}, options);
    }
};

const REMOVE_BLOCKED_REASON = 'Someone has already voted on this request';

const canRemove = (item) => !props.readOnly && item.is_mine === true && item.status !== 'up_next';

const removeBlocked = (item) => item.has_other_votes === true;

const removeRequest = (item) => {
    if (!canRemove(item) || removeBlocked(item) || pending.value === item.id) {
        return;
    }
    router.delete(`/parties/${props.partyCode}/requests/${item.id}`, {
        preserveScroll: true,
        preserveState: true,
        onStart: () => {
            pending.value = item.id;
            errors.value = {...errors.value, [item.id]: null};
        },
        onError: (e) => {
            errors.value = {...errors.value, [item.id]: Object.values(e)[0] ?? 'Could not remove your request.'};
        },
        onFinish: () => {
            pending.value = null;
        },
    });
};

const buttonClass = (active, activeColor) => [
    'flex h-11 w-11 items-center justify-center rounded hover:bg-border disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent',
    active ? activeColor : 'text-text',
];
</script>

<template>
    <p v-if="queue.length === 0" data-testid="queue-empty" class="text-sm text-muted">The queue is empty. Search for a track to request one.</p>
    <ul v-else data-testid="queue-list" class="divide-y divide-border overflow-hidden rounded border border-border bg-surface">
        <li v-for="item in queue" :key="item.id" data-testid="queue-item" class="px-3 py-3 md:px-5 md:py-4">
            <div class="flex items-center gap-3 md:gap-4">
                <TrackLink :href="item.track.provider_url"><TrackThumb :src="item.track.artwork_url" /></TrackLink>
                <div class="min-w-0 flex-1">
                    <div class="truncate text-sm"><TrackLink :href="item.track.provider_url">{{ item.track.title }}</TrackLink></div>
                    <div class="truncate text-sm text-muted">{{ item.track.artists.join(', ') }}</div>
                    <div class="truncate text-xs text-muted">{{ requesterLabel(item) }}</div>
                    <Decorations :decorations="item.decorations" class="mt-1" />
                </div>
                <span class="hidden text-sm tabular-nums text-muted sm:inline">{{ formatDuration(item.track.duration_ms) }}</span>
                <div class="flex items-center">
                    <button
                        type="button"
                        data-testid="vote-up"
                        :aria-label="`Upvote ${item.track.title}`"
                        :aria-pressed="item.my_vote === 1"
                        :disabled="isLocked(item)"
                        :class="buttonClass(item.my_vote === 1, 'text-accent')"
                        @click="vote(item, 'up')"
                    >
                        <Icon name="arrowUp" />
                    </button>
                    <span
                        data-testid="queue-score"
                        class="min-w-8 text-center text-sm tabular-nums"
                        :class="{'text-accent': item.score > 0, 'text-danger': item.score < 0}"
                    >{{ item.score }}</span>
                    <button
                        v-if="downvotesEnabled"
                        type="button"
                        data-testid="vote-down"
                        :aria-label="`Downvote ${item.track.title}`"
                        :aria-pressed="item.my_vote === -1"
                        :disabled="isLocked(item)"
                        :class="buttonClass(item.my_vote === -1, 'text-danger')"
                        @click="vote(item, 'down')"
                    >
                        <Icon name="arrowDown" />
                    </button>
                    <button
                        v-if="canRemove(item)"
                        type="button"
                        data-testid="remove-request"
                        :aria-label="`Remove ${item.track.title}`"
                        :title="removeBlocked(item) ? REMOVE_BLOCKED_REASON : 'Remove your request'"
                        :disabled="removeBlocked(item)"
                        :class="buttonClass(false, '')"
                        @click="removeRequest(item)"
                    >
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            </div>
            <ModSlot name="queue-item" :item="item" />
            <p v-if="errors[item.id]" role="alert" data-testid="vote-error" class="mt-2 text-sm text-danger">{{ errors[item.id] }}</p>
        </li>
    </ul>
</template>
