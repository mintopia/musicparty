<script setup>
import {Head, Link, useForm} from '@inertiajs/vue3';
import {computed, watch} from 'vue';
import Toggle from '../../Components/ui/Toggle.vue';

const props = defineProps({
    providers: {type: Array, default: () => []},
    players: {type: Array, default: () => []},
    spotify_link_url: {type: String, default: null},
    control_link_usable: {type: Boolean, default: true},
});

const form = useForm({
    name: '',
    music_provider: '',
    player_kind: '',
    downvotes: true,
    downvotes_per_hour: null,
    selection_mode: 'deterministic',
    allow_requests: true,
    max_requests: null,
    hold_requests: false,
});

const raffle = computed({
    get: () => form.selection_mode === 'weighted',
    set: (value) => {
        form.selection_mode = value ? 'weighted' : 'deterministic';
    },
});

const needsControlLink = computed(
    () =>
        !props.control_link_usable &&
        props.players.some((player) => player.kind === form.player_kind && player.requiresControlLink),
);

const availablePlayers = computed(() =>
    props.players.filter((player) => player.compatibleProviders.includes(form.music_provider)),
);

watch(
    () => form.music_provider,
    () => {
        form.player_kind = '';
    },
);

const submit = () => form.post('/parties');

const fieldClass =
    'min-h-11 w-full rounded border border-border bg-background px-3 text-base text-text focus-visible:outline-2 focus-visible:outline-primary md:text-sm';
</script>

<template>
    <Head title="Create a party" />
    <div class="px-4 pb-4 pt-8">
        <form
            class="mx-auto flex w-full max-w-md flex-col gap-5 rounded border border-border bg-surface px-5 py-6"
            @submit.prevent="submit"
        >
            <h1 class="text-xl font-semibold">Create a party</h1>

            <div class="flex flex-col gap-1.5">
                <label for="party-name" class="text-sm font-medium">Name</label>
                <input
                    id="party-name"
                    v-model="form.name"
                    type="text"
                    maxlength="64"
                    required
                    :class="fieldClass"
                    :aria-invalid="form.errors.name ? 'true' : undefined"
                />
                <small class="text-xs text-muted">Name for the party</small>
                <p v-if="form.errors.name" role="alert" class="text-sm text-danger">{{ form.errors.name }}</p>
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="party-provider" class="text-sm font-medium">Music provider</label>
                <select
                    id="party-provider"
                    v-model="form.music_provider"
                    required
                    :class="fieldClass"
                    :aria-invalid="form.errors.music_provider ? 'true' : undefined"
                >
                    <option value="" disabled>Choose a provider</option>
                    <option v-for="provider in providers" :key="provider.id" :value="provider.id">
                        {{ provider.label }}
                    </option>
                </select>
                <p v-if="form.errors.music_provider" role="alert" class="text-sm text-danger">
                    {{ form.errors.music_provider }}
                </p>
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="party-player" class="text-sm font-medium">Player</label>
                <select
                    id="party-player"
                    v-model="form.player_kind"
                    required
                    :disabled="!form.music_provider"
                    :class="[fieldClass, 'disabled:opacity-50']"
                    :aria-invalid="form.errors.player_kind ? 'true' : undefined"
                >
                    <option value="" disabled>Choose a player</option>
                    <option v-for="player in availablePlayers" :key="player.kind" :value="player.kind">
                        {{ player.label }}
                    </option>
                </select>
                <p v-if="form.errors.player_kind" role="alert" class="text-sm text-danger">
                    {{ form.errors.player_kind }}
                </p>
            </div>

            <p v-if="needsControlLink" role="alert" class="text-sm text-danger" data-testid="control-link-needed">
                This player needs a linked Spotify account.
                <Link v-if="spotify_link_url" :href="spotify_link_url" data-testid="spotify-link" class="text-primary hover:underline">Link Spotify account</Link>
            </p>

            <fieldset class="flex flex-col gap-3 border-t border-border pt-4">
                <legend class="mb-1 text-base font-semibold">Settings</legend>
                <Toggle v-model="form.allow_requests" label="Allow new songs to be added to the queue" />
                <Toggle v-if="form.allow_requests" v-model="form.hold_requests" label="Hold requests for approval" />
                <Toggle v-model="form.downvotes" label="Allow songs to be downvoted" />
                <Toggle v-model="raffle" label="Treat the queue as a raffle" />
            </fieldset>

            <fieldset class="flex flex-col gap-3 border-t border-border pt-4">
                <legend class="mb-1 text-base font-semibold">Restrictions</legend>
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="flex flex-col gap-1.5">
                        <label for="party-downvotes-per-hour" class="text-sm font-medium">Max downvotes</label>
                        <div class="flex">
                            <input id="party-downvotes-per-hour" v-model.number="form.downvotes_per_hour" type="number" min="0" max="1000" :disabled="!form.downvotes" :class="[fieldClass, 'min-w-0 flex-1 rounded-r-none disabled:opacity-50']" />
                            <span class="flex min-h-11 shrink-0 items-center whitespace-nowrap rounded-r border border-l-0 border-border bg-background px-3 text-sm text-muted">per hour</span>
                        </div>
                        <small class="text-xs text-muted">The maximum number of times a user can downvote in a 1 hour period</small>
                        <p v-if="form.errors.downvotes_per_hour" role="alert" class="text-sm text-danger">{{ form.errors.downvotes_per_hour }}</p>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label for="party-max-requests" class="text-sm font-medium">Max requests</label>
                        <input id="party-max-requests" v-model.number="form.max_requests" type="number" min="1" max="1000" :disabled="!form.allow_requests" :class="[fieldClass, 'disabled:opacity-50']" />
                        <small class="text-xs text-muted">The maximum number of requests that a user can have in the queue</small>
                        <p v-if="form.errors.max_requests" role="alert" class="text-sm text-danger">{{ form.errors.max_requests }}</p>
                    </div>
                </div>
            </fieldset>

            <div class="flex items-center justify-end gap-3 border-t border-border pt-4">
                <Link href="/" class="text-sm text-primary hover:underline">Cancel</Link>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="min-h-11 rounded bg-primary px-4 text-sm font-medium text-white hover:brightness-110 disabled:opacity-50"
                >
                    Create party
                </button>
            </div>
        </form>
    </div>
</template>
