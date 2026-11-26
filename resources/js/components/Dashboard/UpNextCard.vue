<script setup lang="ts">
import {legacyFallbackName} from '@/lib/decoration';
import {formatMs} from '@/lib/dashboard';
import type {DashboardRequest} from '@/types';

defineProps<{request: DashboardRequest}>();
</script>

<template>
    <div data-test="up-next-card">
        <h3>Up Next</h3>
        <div class="card mb-3">
            <div class="list-group card-list-group">
                <div class="list-group-item">
                    <div class="row align-items-center g-3 qrow">
                        <div class="col-auto">
                            <img
                                :src="request.album.image_url"
                                :title="request.album.name"
                                class="rounded"
                                width="60"
                                height="60"
                                alt=""
                            />
                        </div>
                        <div class="col">
                            <div class="title">{{ request.name }}</div>
                            <div class="text-secondary">{{ request.artists.map((a) => a.name).join(', ') }}</div>
                            <div class="text-muted">
                                <template v-if="request.user">Requested by {{ request.user }}</template>
                                <template v-else>{{ legacyFallbackName(request) }}</template>
                            </div>
                        </div>
                        <div class="col-auto text-secondary text-center">
                            {{ formatMs(request.length) }}
                            <div class="text-muted mt-2">{{ request.score }} votes</div>
                        </div>
                        <div v-if="request.sent" class="col-auto">
                            <span class="badge bg-green-lt me-2">Sent to Spotify</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.title {
    font-weight: 500;
}
</style>
