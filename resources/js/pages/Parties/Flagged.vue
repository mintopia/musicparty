<script setup lang="ts">
import {Head, router} from '@inertiajs/vue3';
import axios from 'axios';
import {ref} from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import EmptyState from '@/components/EmptyState.vue';
import TrackRow from '@/components/TrackRow.vue';
import {useEcho} from '@/composables/useEcho';
import {flaggedApproveUrl, flaggedRejectUrl, flaggedUrl} from '@/lib/flagged';
import AppLayout from '@/layouts/AppLayout.vue';
import {home} from '@/routes';
import {show} from '@/routes/parties';
import type {FlaggedRequest} from '@/types';

const props = defineProps<{
    party: {code: string; name: string};
    flagged: FlaggedRequest[];
}>();

const items = ref<FlaggedRequest[]>([...props.flagged]);
const rejecting = ref<FlaggedRequest | null>(null);
const rejectOpen = ref(false);
const rejectMessage = ref('');
const error = ref<string | null>(null);

const channel = useEcho().privateChannel(`party.${props.party.code}.managers`) as {
    listen<T>(event: string, cb: (payload: T) => void): unknown;
};

channel.listen<FlaggedRequest>('FlaggedRequestAdded', (payload) => {
    if (!items.value.some((item) => item.id === payload.id)) {
        items.value = [...items.value, payload];
    }
});

channel.listen<{id: number}>('FlaggedRequestResolved', (payload) => {
    remove(payload.id);
});

function remove(id: number): void {
    items.value = items.value.filter((item) => item.id !== id);
}

async function resolve(item: FlaggedRequest, url: string, data?: Record<string, string>): Promise<void> {
    error.value = null;
    try {
        await axios.post(url, data);
        remove(item.id);
    } catch {
        error.value = 'That request could not be updated.';
        router.reload({only: ['flagged']});
    }
}

function approve(item: FlaggedRequest): Promise<void> {
    return resolve(item, flaggedApproveUrl(props.party.code, item.id));
}

function askReject(item: FlaggedRequest): void {
    rejecting.value = item;
    rejectMessage.value = '';
    rejectOpen.value = true;
}

function confirmReject(): void {
    const item = rejecting.value;
    if (item === null) {
        return;
    }
    const message = rejectMessage.value.trim();
    void resolve(item, flaggedRejectUrl(props.party.code, item.id), message === '' ? undefined : {message});
}
</script>

<template>
    <AppLayout :title="`Flagged requests - ${party.name}`">
        <template #breadcrumbs>
            <li class="breadcrumb-item"><a :href="home().url">Home</a></li>
            <li class="breadcrumb-item">
                <a :href="show(party.code).url">{{ party.name }}</a>
            </li>
            <li class="breadcrumb-item active">
                <a :href="flaggedUrl(party.code)">Flagged</a>
            </li>
        </template>
        <Head :title="`Flagged requests - ${party.name}`" />
        <div class="col-xl-8 offset-xl-2 col-lg-10 offset-lg-1">
            <div class="page-header mt-0">
                <h1>Flagged requests</h1>
            </div>
            <div v-if="error" class="alert alert-danger" role="alert" data-test="error">{{ error }}</div>
            <div class="card">
                <EmptyState v-if="items.length === 0" title="No flagged requests" />
                <div v-else class="list-group list-group-flush">
                    <TrackRow v-for="item in items" :key="item.id" :track="item.track" hide-actions>
                        <template #decoration>
                            <div class="mt-1" data-test="flagged-item">
                                <div data-test="flagged-requester">Requested by {{ item.requester.name }}</div>
                                <div v-if="item.reason" class="text-warning" data-test="flagged-reason">
                                    {{ item.reason }}
                                </div>
                                <div class="mt-2">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-success me-2"
                                        data-test="approve"
                                        @click="approve(item)"
                                    >
                                        Approve
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-danger"
                                        data-test="reject"
                                        @click="askReject(item)"
                                    >
                                        Reject
                                    </button>
                                </div>
                            </div>
                        </template>
                    </TrackRow>
                </div>
            </div>
        </div>
        <ConfirmDialog
            v-model="rejectOpen"
            title="Reject request"
            message="The requester will be told their request was rejected."
            confirm-label="Reject"
            @confirm="confirmReject"
        >
            <textarea
                v-model="rejectMessage"
                class="form-control mt-2"
                rows="2"
                placeholder="Message (optional)"
                data-test="reject-message"
            ></textarea>
        </ConfirmDialog>
    </AppLayout>
</template>
