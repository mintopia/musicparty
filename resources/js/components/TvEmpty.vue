<script setup lang="ts">
defineProps<{
    code: string;
    name: string;
    showQr: boolean;
    qrCodeSrc: string | null;
}>();
</script>

<template>
    <section class="tv-empty" aria-label="Nothing playing" data-test="nothing-playing">
        <div class="tv-empty-copy">
            <h2 class="tv-empty-title">Nothing playing</h2>
            <p>
                {{
                    showQr ? 'Scan to add the first track, or join with code' : 'Join with code to add the first track'
                }}
            </p>
            <b class="tv-empty-code" data-test="join-code">{{ code }}</b>
        </div>
        <img
            v-if="showQr"
            class="tv-qr tv-qr-large"
            :src="qrCodeSrc ?? undefined"
            :alt="`Scan to join ${name}`"
            width="240"
            height="240"
            decoding="async"
            data-test="tv-qr"
        />
    </section>
</template>

<style scoped>
.tv-qr {
    display: block;
    flex: none;
    box-sizing: border-box;
    padding: calc(6 * var(--u));
    border-radius: calc(8 * var(--u));
    background: #fff;
    aspect-ratio: 1;
    object-fit: contain;
}

.tv-qr-large {
    inline-size: clamp(220px, 40vh, 880px);
    block-size: clamp(220px, 40vh, 880px);
    padding: calc(12 * var(--u));
}

.tv-empty {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: calc(56 * var(--u));
}

.tv-empty-copy {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: calc(12 * var(--u));
}

.tv-empty-copy > * {
    margin: 0;
}

.tv-empty-title {
    color: #fff;
    font-size: calc(40 * var(--u));
    line-height: 1.1;
    font-weight: 700;
}

.tv-empty-copy p {
    font-size: calc(19 * var(--u));
    line-height: 1.4;
    color: rgba(255, 255, 255, 0.78);
}

.tv-empty-code {
    display: block;
    line-height: 1.1;
    font-size: calc(40 * var(--u));
    font-weight: 700;
    letter-spacing: 0.06em;
    font-variant-numeric: tabular-nums;
}
</style>
