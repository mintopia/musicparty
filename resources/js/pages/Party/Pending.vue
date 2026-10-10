<script setup>
import {Head, Link, router, usePage} from '@inertiajs/vue3';
import {computed, ref} from 'vue';

const props = defineProps({
    party: {type: Object, required: true},
    canModerate: {type: Boolean, required: true},
    requests: {type: Array, required: true},
});

const page = usePage();
const successMessage = computed(() => page.props.flash?.successMessage ?? null);
const reasons = ref({});
const base = computed(() => `/parties/${props.party.code}/requests`);

const approve = (id) => router.post(`${base.value}/${id}/approve`, {}, {preserveScroll: true});
const reject = (id) => router.post(`${base.value}/${id}/reject`, {reason: reasons.value[id] || null}, {preserveScroll: true});
const remove = (id) => router.delete(`${base.value}/${id}`, {preserveScroll: true});
</script>

<template>
    <Head :title="`Pending requests - ${party.name}`" />
    <div class="flex flex-col gap-4 px-4 pb-4 pt-6">
        <header class="flex flex-wrap items-center justify-between gap-2 rounded border border-border bg-surface px-5 py-5">
            <h1 class="text-xl font-semibold">Pending requests</h1>
            <Link
                :href="`/parties/${party.code}`"
                class="flex min-h-11 items-center rounded border border-border px-3 text-sm hover:text-primary"
            >Back to {{ party.name }}</Link>
        </header>

        <p v-if="successMessage" role="status" class="rounded border border-border bg-surface px-5 py-3 text-sm">{{ successMessage }}</p>
        <p v-if="requests.length === 0" data-testid="pending-empty" class="text-sm text-muted">Nothing is waiting for approval.</p>

        <ul class="flex flex-col gap-3">
            <li v-for="item in requests" :key="item.id" :data-testid="`pending-${item.id}`" class="flex flex-col gap-2 rounded border border-border bg-surface px-5 py-4">
                <div>
                    <p class="font-medium">{{ item.track.title }}</p>
                    <p class="text-sm text-muted">{{ item.track.artists.join(', ') }} - requested by {{ item.requested_by.name }}</p>
                </div>
                <div v-if="canModerate" class="flex flex-wrap items-center gap-2">
                    <button type="button" class="min-h-11 rounded border border-border px-3 text-sm hover:text-primary" @click="approve(item.id)">Approve</button>
                    <input v-model="reasons[item.id]" type="text" maxlength="200" placeholder="Reason (optional)" class="min-h-11 rounded border border-border bg-surface px-2 text-sm">
                    <button type="button" class="min-h-11 rounded border border-border px-3 text-sm hover:text-primary" @click="reject(item.id)">Reject</button>
                </div>
                <div>
                    <button type="button" class="min-h-11 rounded border border-border px-3 text-sm hover:text-primary" @click="remove(item.id)">Remove</button>
                </div>
            </li>
        </ul>
    </div>
</template>
