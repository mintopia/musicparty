<script setup>
import {Head, useForm} from '@inertiajs/vue3';
import {computed, watch} from 'vue';

const props = defineProps({
    providers: {type: Array, default: () => []},
    players: {type: Array, default: () => []},
});

const form = useForm({name: '', music_provider: '', player_kind: ''});

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

            <button
                type="submit"
                :disabled="form.processing"
                class="min-h-11 rounded bg-primary px-4 text-sm font-medium text-white hover:brightness-110 disabled:opacity-50"
            >
                Create party
            </button>
        </form>
    </div>
</template>
