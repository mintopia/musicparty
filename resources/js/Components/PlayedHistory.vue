<script setup>
import {Link, router} from '@inertiajs/vue3';
import {computed, ref} from 'vue';
import Icon from './Icon.vue';
import TrackThumb from './TrackThumb.vue';
import {formatPlayedAt, requesterLabel} from '../lib/format';

const props = defineProps({
    history: {type: Object, default: null},
    partyCode: {type: String, default: ''},
    readOnly: {type: Boolean, default: false},
});

const rows = computed(() => props.history?.data ?? []);
const prevUrl = computed(() => props.history?.links?.prev ?? null);
const nextUrl = computed(() => props.history?.links?.next ?? null);

const pending = ref(null);
const errors = ref({});

const rate = (play, direction) => {
    if (props.readOnly || pending.value === play.id) {
        return;
    }
    const value = direction === 'up' ? 1 : -1;
    const url = `/parties/${props.partyCode}/plays/${play.id}/rating`;
    const options = {
        preserveScroll: true,
        preserveState: true,
        onStart: () => {
            pending.value = play.id;
            errors.value = {...errors.value, [play.id]: null};
        },
        onError: (e) => {
            errors.value = {...errors.value, [play.id]: e.rating ?? e.value ?? 'Could not record your rating.'};
        },
        onFinish: () => {
            pending.value = null;
        },
    };

    if (play.my_rating === value) {
        router.delete(url, options);
    } else {
        router.put(url, {value: direction}, options);
    }
};

const buttonClass = (active, activeColor) => [
    'flex h-11 min-w-11 items-center justify-center gap-1 rounded px-2 text-sm tabular-nums hover:bg-border disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent',
    active ? activeColor : 'text-text',
];
</script>

<template>
    <p v-if="rows.length === 0" data-testid="history-empty" class="text-sm text-muted">Nothing has been played yet.</p>
    <template v-else>
        <ul data-testid="history-list" class="divide-y divide-border overflow-hidden rounded border border-border bg-surface">
            <li v-for="play in rows" :key="play.id" data-testid="history-item" class="px-3 py-3 md:px-5 md:py-4">
                <div class="flex items-center gap-3 md:gap-4">
                    <TrackThumb :src="play.track.artwork_url" />
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm">{{ play.track.title }}</div>
                        <div class="truncate text-sm text-muted">{{ play.track.artists.join(', ') }}</div>
                        <div class="truncate text-xs text-muted">
                            {{ requesterLabel(play) }}<span class="md:hidden"> · {{ formatPlayedAt(play.played_at) }}</span>
                        </div>
                    </div>
                    <time data-testid="history-played-at" :datetime="play.played_at" class="hidden text-sm text-muted md:inline">{{ formatPlayedAt(play.played_at) }}</time>
                    <div class="flex items-center">
                        <button
                            type="button"
                            data-testid="rate-like"
                            :aria-label="`Like ${play.track.title}`"
                            :aria-pressed="play.my_rating === 1"
                            :disabled="readOnly"
                            :class="buttonClass(play.my_rating === 1, 'text-accent')"
                            @click="rate(play, 'up')"
                        >
                            <Icon name="thumbUp" />
                            <span data-testid="history-likes">{{ play.likes }}</span>
                        </button>
                        <button
                            type="button"
                            data-testid="rate-dislike"
                            :aria-label="`Dislike ${play.track.title}`"
                            :aria-pressed="play.my_rating === -1"
                            :disabled="readOnly"
                            :class="buttonClass(play.my_rating === -1, 'text-danger')"
                            @click="rate(play, 'down')"
                        >
                            <Icon name="thumbDown" />
                            <span data-testid="history-dislikes">{{ play.dislikes }}</span>
                        </button>
                    </div>
                </div>
                <p v-if="errors[play.id]" role="alert" data-testid="rating-error" class="mt-2 text-sm text-danger">{{ errors[play.id] }}</p>
            </li>
        </ul>
        <nav v-if="prevUrl || nextUrl" class="mt-4 flex items-center justify-between gap-2" aria-label="Pagination">
            <Link
                v-if="prevUrl"
                :href="prevUrl"
                data-testid="history-prev"
                class="flex min-h-11 items-center rounded border border-border px-3 text-sm hover:text-primary"
            >Previous</Link>
            <span v-else />
            <Link
                v-if="nextUrl"
                :href="nextUrl"
                data-testid="history-next"
                class="flex min-h-11 items-center rounded border border-border px-3 text-sm hover:text-primary"
            >Next</Link>
        </nav>
    </template>
</template>
