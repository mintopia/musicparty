<script setup lang="ts">
import {computed} from 'vue';
import type {PlaybackStatus} from '@/lib/playbackStatus';
import type {DashboardControl} from '@/types';

const props = defineProps<{
    health: DashboardControl['health'];
    status: PlaybackStatus;
    webPlayerUrl: string | null;
    relinkUrl: string;
}>();

const live = computed(() => props.health.player.connected === true);
const relink = computed(() => props.health.link === 'relink_required' || props.status.kind === 'relink');
const pollingLabel = computed(() => ({off: 'Off', on: 'On', backup: 'On, as backup'})[props.health.polling]);
const pollingDetail = computed(
    () =>
        ({
            off: 'Spotify is not polled; the Player reports playback.',
            on: 'Spotify is polled for playback.',
            backup: 'Spotify is polled only while the Player is not reporting.',
        })[props.health.polling],
);
</script>

<template>
    <div class="health-wrap" data-test="health-grid">
        <div class="health">
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
                <div class="val" :class="{warn: status.age.stale}" data-test="health-age">
                    <template v-if="live"><span class="status-dot status-green me-2"></span>Live</template>
                    <template v-else-if="status.age.stale"
                        ><i class="ti ti-alert-triangle"></i> {{ status.age.text }}</template
                    >
                    <template v-else>{{ status.age.text }}</template>
                </div>
                <a v-if="status.age.stale && webPlayerUrl" :href="webPlayerUrl" class="fix" data-test="health-fix"
                    >Open Web Player</a
                >
            </div>
            <div>
                <div class="lbl">Spotify link</div>
                <div class="val" :class="{warn: relink}" data-test="health-link">
                    <template v-if="!relink"><span class="status-dot status-green me-2"></span>Connected</template>
                    <template v-else><i class="ti ti-alert-triangle"></i> Re-link needed</template>
                </div>
                <a v-if="relink" :href="relinkUrl" class="fix" data-test="health-relink">Re-link Spotify</a>
            </div>
            <div>
                <div class="lbl">Polling</div>
                <div class="val" data-test="health-polling">{{ pollingLabel }}</div>
            </div>
        </div>
        <details class="diag" data-test="diagnostics">
            <summary>Diagnostics</summary>
            <dl class="diag-list">
                <dt>Player source</dt>
                <dd data-test="diag-source">{{ health.player.source ?? 'None' }}</dd>
                <dt>Device ID</dt>
                <dd data-test="diag-device-id">{{ health.device.id ?? 'None' }}</dd>
                <dt>Last report</dt>
                <dd data-test="diag-received-at">{{ health.receivedAt ?? 'Never' }}</dd>
                <dt>Polling</dt>
                <dd data-test="diag-polling">{{ pollingDetail }}</dd>
            </dl>
        </details>
    </div>
</template>

<style scoped>
.health-wrap {
    margin-top: 16px;
    border-top: 1px solid var(--mp-deck-divider);
}
.health {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
}
.health > div {
    padding: 10px 12px 0 0;
    min-width: 0;
}
.lbl {
    font-size: 0.72rem;
    color: var(--mp-deck-muted);
}
.val {
    font-weight: 600;
    font-size: 0.875rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.fix {
    font-size: 0.8rem;
    color: var(--mp-deck-text);
    text-decoration: underline;
}
.diag {
    margin-top: 10px;
}
.diag summary {
    cursor: pointer;
    font-size: 0.8rem;
    color: var(--mp-deck-muted);
}
.diag-list {
    display: grid;
    grid-template-columns: max-content minmax(0, 1fr);
    gap: 2px 14px;
    margin: 6px 0 0;
    font-size: 0.8rem;
}
.diag-list dt {
    color: var(--mp-deck-muted);
    font-weight: 400;
}
.diag-list dd {
    margin: 0;
    overflow-wrap: anywhere;
}
.warn {
    color: var(--mp-deck-accent-warn);
}
@container (max-width: 620px) {
    .health {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
</style>
