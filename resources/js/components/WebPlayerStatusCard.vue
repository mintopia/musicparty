<script setup lang="ts">
import type {WebPlayerCheck} from '@/composables/useWebPlayerChecks';

defineProps<{
    checks: WebPlayerCheck[];
    showLeaveNotice: boolean;
}>();
</script>

<template>
    <div class="card">
        <div class="card-header"><h2 class="card-title">Player status</h2></div>
        <ul class="mp-checks" data-test="player-checks">
            <li v-for="check in checks" :key="check.label">
                <span class="status-dot" :class="`status-${check.tone}`" aria-hidden="true"></span>
                <span>
                    <span class="mp-check-label">{{ check.label }}</span>
                    <span class="mp-check-detail">{{ check.detail }}</span>
                </span>
                <a
                    v-if="check.action"
                    class="btn btn-sm"
                    :class="check.action.primary ? 'btn-primary' : 'btn-outline-primary'"
                    :href="check.action.href"
                    >{{ check.action.label }}</a
                >
                <span v-else></span>
            </li>
        </ul>
    </div>
    <div v-if="showLeaveNotice" class="card">
        <div class="card-body wp-leave">
            <i class="icon ti ti-volume fs-2 text-secondary" aria-hidden="true"></i>
            <div>
                <b>This tab is the party's speaker.</b>
                <div class="text-secondary">
                    Keep it open. Closing it or navigating away stops playback, and the browser asks you to confirm.
                </div>
            </div>
        </div>
    </div>
</template>
