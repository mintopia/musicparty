<script setup>
import {Head} from '@inertiajs/vue3';
import {computed, ref} from 'vue';
import Icon from '../../Components/Icon.vue';

const props = defineProps({
    party: {type: Object, required: true},
    membership: {type: Object, required: true},
    section: {type: String, default: 'queue'},
    readOnly: {type: Boolean, default: false},
    nowPlaying: {type: Object, default: null},
});

const copied = ref(false);
const copyCode = async () => {
    try {
        await navigator.clipboard.writeText(props.party.code);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch {
        copied.value = false;
    }
};

const stateClass = computed(
    () =>
        ({
            live: 'bg-accent text-white',
            paused: 'border border-border text-muted',
            ended: 'bg-danger text-white',
        })[props.party.state] ?? 'border border-border text-muted',
);

const readOnlyMessage = computed(() =>
    props.membership.banned ? 'You have been banned from this party.' : 'This party has ended.',
);

const placeholders = {
    queue: 'The queue is empty.',
    search: 'Search is coming soon.',
    history: 'Nothing has been played yet.',
};
</script>

<template>
    <Head :title="party.name" />
    <div class="flex flex-col gap-4 px-4 pb-4 pt-6">
        <header class="flex flex-col gap-3 rounded border border-border bg-surface px-5 py-5">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-xl font-semibold">{{ party.name }}</h1>
                <span
                    data-testid="party-state"
                    class="rounded px-2 py-0.5 text-xs font-medium capitalize"
                    :class="stateClass"
                >{{ party.state }}</span>
            </div>
            <div class="flex items-center gap-3">
                <span data-testid="party-code" class="text-4xl font-semibold tracking-widest">{{ party.code }}</span>
                <button
                    type="button"
                    class="flex min-h-11 items-center gap-2 rounded border border-border px-3 text-sm hover:text-primary"
                    @click="copyCode"
                >
                    <Icon name="copy" />
                    <span aria-live="polite">{{ copied ? 'Copied' : 'Copy' }}</span>
                </button>
            </div>
        </header>

        <div
            v-if="readOnly"
            role="status"
            data-testid="read-only-banner"
            class="rounded border border-danger bg-surface px-4 py-3 text-sm text-danger"
        >
            {{ readOnlyMessage }}
        </div>

        <section class="rounded border border-border bg-surface px-5 py-5">
            <dl v-if="section === 'party'" class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
                <dt class="text-muted">Code</dt>
                <dd>{{ party.code }}</dd>
                <dt class="text-muted">Provider</dt>
                <dd>{{ party.musicProvider }}</dd>
                <dt class="text-muted">Player</dt>
                <dd>{{ party.playerKind }}</dd>
                <dt class="text-muted">Role</dt>
                <dd class="capitalize" data-testid="party-role">Your role: {{ membership.role }}</dd>
            </dl>
            <p v-else class="text-sm text-muted">{{ placeholders[section] }}</p>
        </section>
    </div>
</template>
