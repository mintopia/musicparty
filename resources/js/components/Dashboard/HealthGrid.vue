<script setup lang="ts">
import {computed} from 'vue';
import {useTicker} from '@/composables/useProgress';
import type {DashboardControl} from '@/types';

const STALE_SECONDS = 60;

const props = defineProps<{health: DashboardControl['health']; playingFrom: DashboardControl['playingFrom']}>();

const now = useTicker(
    () => props.health.sampledAt !== null,
    () => props.health.sampledAt,
);

const ageSeconds = computed(() =>
    props.health.sampledAt === null
        ? null
        : Math.max(0, Math.floor((now.value - Date.parse(props.health.sampledAt)) / 1000)),
);
const stale = computed(() => ageSeconds.value !== null && ageSeconds.value > STALE_SECONDS);
const ageLabel = computed(() => {
    const seconds = ageSeconds.value;
    if (seconds === null) {
        return 'Never';
    }
    return seconds < 120 ? `${seconds} s ago` : `${Math.floor(seconds / 60)} min ago`;
});
const pollingLabel = computed(() => ({off: 'Off', on: 'On', backup: 'On, as backup'})[props.health.polling]);
const playingFromWarns = computed(() => props.playingFrom.kind === 'outside' || props.playingFrom.kind === 'unknown');
const playingFromLabel = computed(() => {
    switch (props.playingFrom.kind) {
        case 'party':
            return 'The party';
        case 'outside':
            return props.playingFrom.label ?? 'Outside the party';
        case 'unknown':
            return 'Unknown';
        default:
            return 'Nothing';
    }
});
</script>

<template>
    <div class="health" data-test="health-grid">
        <div>
            <div class="lbl">Player</div>
            <div class="val" data-test="health-player">
                <span
                    class="status-dot me-2"
                    :class="health.player.fresh ? 'status-dot-animated status-green' : 'status-secondary'"
                ></span
                >{{ health.player.label }}
            </div>
        </div>
        <div>
            <div class="lbl">Device</div>
            <div class="val" data-test="health-device">{{ health.device.name ?? 'None' }}</div>
        </div>
        <div>
            <div class="lbl">Last update</div>
            <div class="val" :class="{warn: stale}" data-test="health-age">
                <template v-if="stale"><i class="ti ti-alert-triangle"></i> {{ ageLabel }}, stale</template>
                <template v-else>{{ ageLabel }}</template>
            </div>
        </div>
        <div>
            <div class="lbl">Playing from</div>
            <div class="val" :class="{warn: playingFromWarns}" data-test="health-from">
                <i v-if="playingFromWarns" class="ti ti-alert-triangle"></i>
                <span v-else-if="playingFrom.kind === 'party'" class="status-dot status-green me-2"></span>
                {{ playingFromLabel }}
            </div>
        </div>
        <div>
            <div class="lbl">Spotify link</div>
            <div class="val" :class="{warn: health.link === 'relink_required'}" data-test="health-link">
                <template v-if="health.link === 'connected'"
                    ><span class="status-dot status-green me-2"></span>Connected</template
                >
                <template v-else><i class="ti ti-alert-triangle"></i> Re-link needed</template>
            </div>
        </div>
        <div>
            <div class="lbl">Polling</div>
            <div class="val" data-test="health-polling">{{ pollingLabel }}</div>
        </div>
    </div>
</template>

<style scoped>
.health {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    margin-top: 16px;
    border-top: 1px solid rgba(255, 255, 255, 0.12);
}
.health > div {
    padding: 10px 12px 0 0;
    min-width: 0;
}
.lbl {
    font-size: 0.72rem;
    color: rgba(255, 255, 255, 0.6);
}
.val {
    font-weight: 600;
    font-size: 0.875rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.warn {
    color: #fde68a;
}
@container (max-width: 620px) {
    .health {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
</style>
