<script setup>
import {router} from '@inertiajs/vue3';
import {computed, ref} from 'vue';
import Icon from './Icon.vue';

const props = defineProps({
    nowPlaying: {type: Object, required: true},
    partyCode: {type: String, default: ''},
    myRating: {type: Number, default: 0},
    disabled: {type: Boolean, default: false},
});

const pending = ref(false);
const error = ref(null);

const net = computed(() => (props.nowPlaying.likes ?? 0) - (props.nowPlaying.dislikes ?? 0));

const rate = (direction) => {
    if (props.disabled || pending.value) {
        return;
    }
    const value = direction === 'up' ? 1 : -1;
    const url = `/parties/${props.partyCode}/requests/${props.nowPlaying.id}/rating`;
    const options = {
        preserveScroll: true,
        preserveState: true,
        onStart: () => {
            pending.value = true;
            error.value = null;
        },
        onError: (e) => {
            error.value = e.rating ?? e.value ?? 'Could not record your rating.';
        },
        onFinish: () => {
            pending.value = false;
        },
    };

    if (props.myRating === value) {
        router.delete(url, options);
    } else {
        router.put(url, {value: direction}, options);
    }
};

const buttonClass = (active, activeColor) => [
    'flex h-11 w-11 items-center justify-center rounded hover:bg-white/10 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent',
    active ? activeColor : '',
];
</script>

<template>
    <div data-testid="rating" class="flex flex-col items-center">
        <div class="flex items-center justify-center gap-1">
            <button
                type="button"
                data-testid="rating-dislike"
                aria-label="Dislike"
                :aria-pressed="myRating === -1"
                :disabled="disabled || pending"
                :class="buttonClass(myRating === -1, 'text-danger')"
                @click="rate('down')"
            ><Icon name="thumbDown" /></button>
            <span data-testid="rating-count" class="min-w-6 text-center text-sm tabular-nums" :title="`${nowPlaying.likes ?? 0} likes, ${nowPlaying.dislikes ?? 0} dislikes`">{{ net }}</span>
            <button
                type="button"
                data-testid="rating-like"
                aria-label="Like"
                :aria-pressed="myRating === 1"
                :disabled="disabled || pending"
                :class="buttonClass(myRating === 1, 'text-accent')"
                @click="rate('up')"
            ><Icon name="thumbUp" /></button>
        </div>
        <p v-if="error" role="alert" data-testid="rating-error" class="text-xs text-danger">{{ error }}</p>
    </div>
</template>
