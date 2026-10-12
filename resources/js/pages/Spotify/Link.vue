<script setup>
import {Head, Link, router} from '@inertiajs/vue3';
import {computed} from 'vue';

const props = defineProps({
    control: {type: Object, required: true},
    can_create_party: {type: Boolean, default: false},
    link_url: {type: String, required: true},
    unlink_url: {type: String, required: true},
    create_party_url: {type: String, required: true},
});

const canCreate = computed(() => props.can_create_party && props.control.linked && !props.control.needs_relink);

const unlink = () => {
    if (!window.confirm('Unlink your Spotify account?')) {
        return;
    }
    router.delete(props.unlink_url);
};

const buttonClass = 'flex min-h-11 w-full items-center justify-center rounded px-4 text-sm font-medium text-white hover:brightness-110';
</script>

<template>
    <Head title="Link Spotify Account" />
    <div class="flex flex-col gap-4 px-4 pb-4 pt-6">
        <h1 class="text-xl font-semibold">Link Spotify Account</h1>
        <p class="text-sm">For Music Party to work, we need to link your Spotify account so it can control playback for your party.</p>

        <div class="mx-auto flex w-full max-w-md flex-col gap-4">
            <div class="flex flex-col gap-3 rounded border border-border bg-surface px-5 py-6" data-testid="control-card">
                <template v-if="control.linked">
                    <div class="text-center font-medium text-green-500" data-testid="linked-name">{{ control.name }}</div>
                    <p v-if="control.needs_relink" class="text-center text-sm" data-testid="relink-notice">Re-link needed for playback</p>
                    <a v-if="control.needs_relink" :href="link_url" data-testid="relink" :class="[buttonClass, 'bg-green-600']">Re-link with Spotify</a>
                    <button type="button" data-testid="unlink" class="min-h-11 rounded border border-border px-4 text-sm hover:text-primary" @click="unlink">Unlink</button>
                </template>
                <a v-else :href="link_url" data-testid="link" :class="[buttonClass, 'bg-green-600']">Link with Spotify</a>
            </div>

            <Link v-if="canCreate" :href="create_party_url" data-testid="create-party" :class="[buttonClass, 'bg-primary']">Create Party</Link>
            <button v-else type="button" disabled data-testid="create-party" :class="[buttonClass, 'bg-primary opacity-50']">Create Party</button>
        </div>
    </div>
</template>
