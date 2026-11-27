<script setup lang="ts">
import {VueEternalLoading} from '@ts-pro/vue-eternal-loading';
import type {LoadAction} from '@ts-pro/vue-eternal-loading';
import QueueEmpty from '@/components/Upcoming/QueueEmpty.vue';
import TrackRowPlaceholder from '@/components/TrackRowPlaceholder.vue';

defineProps<{load: (action: LoadAction) => Promise<void>}>();
</script>

<template>
    <VueEternalLoading :load="load">
        <template #loading>
            <div class="card" aria-hidden="true">
                <div class="list-group list-group-flush">
                    <TrackRowPlaceholder v-for="n in 3" :key="n" />
                </div>
            </div>
        </template>

        <template #no-results>
            <div class="card">
                <QueueEmpty />
            </div>
        </template>

        <template #no-more><span /></template>

        <template #error="{retry}">
            <div class="card" role="alert" data-test="queue-error">
                <div class="card-body text-center py-5">
                    <i class="icon ti ti-cloud-off text-secondary fs-1"></i>
                    <h2 class="h3 mt-3 mb-1">The Queue didn&rsquo;t load</h2>
                    <div class="text-secondary mb-3">Check your connection, then try again. Your votes are saved.</div>
                    <button type="button" class="btn btn-primary" data-test="retry" @click="retry">
                        <i class="icon ti ti-refresh me-1"></i>Retry
                    </button>
                </div>
            </div>
        </template>
    </VueEternalLoading>
</template>
