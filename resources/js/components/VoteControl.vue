<script setup lang="ts">
withDefaults(
    defineProps<{
        score: number;
        vote?: number | null;
        canDownvote?: boolean;
        label?: string;
    }>(),
    {vote: 0, canDownvote: false, label: undefined},
);

const emit = defineEmits<{vote: [value: -1 | 0 | 1]}>();
</script>

<template>
    <div class="vote-control btn-list flex-nowrap justify-content-center align-items-center gap-1">
        <template v-if="canDownvote">
            <button
                type="button"
                class="btn btn-sm btn-link vote-button"
                :aria-label="label ? `Upvote ${label}` : 'Upvote'"
                :aria-pressed="vote === 1"
                data-test="upvote"
                @click="emit('vote', vote === 1 ? 0 : 1)"
            >
                <i class="icon ti" :class="vote === 1 ? 'ti-arrow-big-up-filled text-success' : 'ti-arrow-big-up'"></i>
            </button>
            <span class="vote-score" data-test="score">{{ score }}</span>
            <button
                type="button"
                class="btn btn-sm btn-link vote-button"
                :aria-label="label ? `Downvote ${label}` : 'Downvote'"
                :aria-pressed="vote === -1"
                data-test="downvote"
                @click="emit('vote', vote === -1 ? 0 : -1)"
            >
                <i
                    class="icon ti"
                    :class="vote === -1 ? 'ti-arrow-big-down-filled text-danger' : 'ti-arrow-big-down'"
                ></i>
            </button>
        </template>
        <template v-else>
            <button
                type="button"
                class="btn btn-sm btn-link vote-button"
                :aria-label="label ? `Like ${label}` : 'Like'"
                :aria-pressed="vote === 1"
                data-test="heart"
                @click="emit('vote', vote === 1 ? 0 : 1)"
            >
                <i class="icon ti" :class="vote === 1 ? 'ti-heart-filled text-success' : 'ti-heart'"></i>
            </button>
            <span class="vote-score" data-test="score">{{ score }}</span>
        </template>
    </div>
</template>

<style scoped>
.vote-button,
.vote-button:hover,
.vote-button:focus,
.vote-button:active {
    text-decoration: none;
}

.vote-button {
    min-width: 28px;
    min-height: 28px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.vote-score {
    min-width: 1.5ch;
    text-align: center;
}

@media (pointer: coarse) {
    .vote-button {
        min-width: 44px;
        min-height: 44px;
    }
}
</style>
