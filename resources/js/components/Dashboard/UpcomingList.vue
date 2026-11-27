<script setup lang="ts">
import axios from 'axios';
import EmptyState from '@/components/EmptyState.vue';
import {formatMs, useRun} from '@/lib/dashboard';
import {legacyFallbackName} from '@/lib/decoration';
import {store as storeVote} from '@/routes/api/v1/parties/upcomingsongs/vote';
import type {DashboardRequest} from '@/types';

const props = defineProps<{
    code: string;
    items: DashboardRequest[];
    total: number;
    queueUrl: string;
    fallbackSettingsUrl: string;
    hasFallbackPlaylist: boolean;
    canModerate: boolean;
    canDownvote: boolean;
    timezone: string;
}>();
const emit = defineEmits<{delete: [request: DashboardRequest]}>();

const run = useRun();

function formatScheduled(iso: string): string {
    return new Intl.DateTimeFormat(undefined, {timeStyle: 'short', timeZone: props.timezone}).format(new Date(iso));
}

async function vote(request: DashboardRequest, value: number): Promise<void> {
    const route = storeVote({party: props.code, upcomingsong: request.id});
    await run(() => axios.request({url: route.url, method: route.method, data: {vote: value}}), ['upNext', 'upcoming']);
}
</script>

<template>
    <div class="upcoming-list" data-test="upcoming-list">
        <div class="hdr d-flex align-items-center mt-4 mb-2">
            <h3 class="mb-0 flex-grow-1">Upcoming Songs</h3>
            <a :href="queueUrl" data-test="manage-link"
                >Manage all {{ total }} requests <i class="ti ti-chevron-right"></i
            ></a>
        </div>
        <div class="card">
            <EmptyState v-if="items.length === 0" icon="ti-playlist-off" title="Queue empty">
                <a
                    v-if="!hasFallbackPlaylist"
                    :href="fallbackSettingsUrl"
                    class="btn btn-outline-secondary"
                    data-test="set-fallback"
                    >Set fallback</a
                >
            </EmptyState>
            <div v-else class="list-group card-list-group">
                <div v-for="request in items" :key="request.id" class="list-group-item py-2" data-test="upcoming-row">
                    <div class="row align-items-center g-3 qrow">
                        <div class="col-auto">
                            <img
                                :src="request.album.image_url"
                                :title="request.album.name"
                                class="rounded"
                                width="44"
                                height="44"
                                alt=""
                            />
                        </div>
                        <div class="col">
                            <div class="title">{{ request.name }}</div>
                            <div class="text-secondary">{{ request.artists.map((a) => a.name).join(', ') }}</div>
                            <div v-if="request.scheduled_for" class="text-info small">
                                <i class="icon ti ti-clock"></i> Scheduled for
                                {{ formatScheduled(request.scheduled_for) }}
                            </div>
                            <div class="text-muted small">
                                <template v-if="request.user">Requested by {{ request.user }}</template>
                                <template v-else>Fallback: {{ legacyFallbackName(request) }}</template>
                            </div>
                        </div>
                        <div class="col-auto text-secondary">{{ formatMs(request.length) }}</div>
                        <div v-if="request.sent" class="col-auto" data-test="sent-badge">
                            <span class="badge bg-green-lt" title="Already in Spotify's queue">Sent to Spotify</span>
                        </div>
                        <div v-if="!request.sent" class="col-auto votes">
                            <template v-if="canDownvote">
                                <button
                                    type="button"
                                    class="vote-btn"
                                    :class="{'text-success fw-bold': request.vote === 1}"
                                    :aria-label="request.vote === 1 ? 'Remove upvote' : 'Upvote'"
                                    data-test="vote-up"
                                    @click="vote(request, request.vote === 1 ? 0 : 1)"
                                >
                                    <i
                                        class="ti"
                                        :class="request.vote === 1 ? 'ti-arrow-big-up-filled' : 'ti-arrow-big-up'"
                                    ></i>
                                </button>
                                <span class="mx-2">{{ request.score }}</span>
                                <button
                                    type="button"
                                    class="vote-btn"
                                    :class="{'text-danger fw-bold': request.vote === -1}"
                                    :aria-label="request.vote === -1 ? 'Remove downvote' : 'Downvote'"
                                    data-test="vote-down"
                                    @click="vote(request, request.vote === -1 ? 0 : -1)"
                                >
                                    <i
                                        class="ti"
                                        :class="request.vote === -1 ? 'ti-arrow-big-down-filled' : 'ti-arrow-big-down'"
                                    ></i>
                                </button>
                            </template>
                            <template v-else>
                                <button
                                    type="button"
                                    class="vote-btn"
                                    :class="{'text-success': request.vote === 1}"
                                    :aria-label="request.vote === 1 ? 'Remove vote' : 'Vote'"
                                    data-test="vote-up"
                                    @click="vote(request, request.vote === 1 ? 0 : 1)"
                                >
                                    <i class="ti" :class="request.vote === 1 ? 'ti-heart-filled' : 'ti-heart'"></i>
                                </button>
                                <span class="mx-2">{{ request.score }}</span>
                            </template>
                        </div>
                        <div v-if="!request.sent" class="col-auto qa">
                            <a
                                :href="`${queueUrl}/${request.id}`"
                                class="btn btn-ghost-secondary btn-icon"
                                aria-label="Schedule"
                                title="Schedule"
                                data-test="schedule"
                                ><i class="ti ti-clock"></i
                            ></a>
                            <button
                                type="button"
                                class="btn btn-ghost-danger btn-icon"
                                aria-label="Remove request"
                                :title="canModerate ? 'Remove request' : 'Only moderators can remove requests'"
                                :disabled="!canModerate"
                                data-test="remove"
                                @click="emit('delete', request)"
                            >
                                <i class="ti ti-x"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.upcoming-list:first-child > .hdr {
    margin-top: 0 !important;
}
.title {
    font-weight: 500;
}
.votes {
    display: flex;
    align-items: center;
    gap: 4px;
    min-width: 88px;
    justify-content: center;
}
.votes .ti {
    font-size: 1.25rem;
}
.vote-btn {
    background: none;
    border: 0;
    padding: 0;
    color: inherit;
    line-height: 1;
    cursor: pointer;
}
.qa .btn {
    width: 36px;
    height: 36px;
    padding: 0;
}
</style>
