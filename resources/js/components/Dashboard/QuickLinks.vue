<script setup lang="ts">
import {computed, onBeforeUnmount, ref} from 'vue';
import type {DashboardProps} from '@/types';

const props = defineProps<{links: DashboardProps['links']}>();

const copied = ref(false);
let timer: ReturnType<typeof setTimeout> | undefined;

const items = computed(() => [
    {icon: 'ti-settings', label: 'Settings', href: props.links.settings, external: false},
    {icon: 'ti-users', label: 'Members', href: props.links.members, external: false},
    {icon: 'ti-shield', label: 'Moderation', href: props.links.moderation, external: false},
    {icon: 'ti-puzzle', label: 'Mods', href: props.links.mods, external: false},
    ...(props.links.webPlayer
        ? [{icon: 'ti-player-play', label: 'Web Player', href: props.links.webPlayer, external: false}]
        : []),
    {icon: 'ti-device-tv', label: 'TV view', href: props.links.tv, external: true},
    {icon: 'ti-broadcast', label: 'Encore relay', href: props.links.encoreRelay, external: false},
]);

function fallbackCopy(text: string): boolean {
    const area = document.createElement('textarea');
    area.value = text;
    area.setAttribute('readonly', '');
    area.style.position = 'fixed';
    area.style.opacity = '0';
    document.body.appendChild(area);
    area.select();
    try {
        return document.execCommand('copy');
    } finally {
        document.body.removeChild(area);
    }
}

async function copyJoin(): Promise<void> {
    let ok = false;
    try {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(props.links.join);
            ok = true;
        }
    } catch {
        ok = false;
    }
    if (!ok) {
        ok = fallbackCopy(props.links.join);
    }
    if (!ok) {
        return;
    }
    copied.value = true;
    clearTimeout(timer);
    timer = setTimeout(() => {
        copied.value = false;
    }, 2000);
}

onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <div class="card" data-test="quick-links">
        <div class="card-header"><h3 class="card-title">Quick links</h3></div>
        <div class="card-body">
            <div class="row g-2 ql-grid">
                <div v-for="item in items" :key="item.label" class="col-6">
                    <a
                        :href="item.href"
                        class="btn btn-outline-secondary w-100"
                        :target="item.external ? '_blank' : undefined"
                        :rel="item.external ? 'noopener' : undefined"
                        ><i class="ti" :class="item.icon"></i>{{ item.label }}</a
                    >
                </div>
                <div class="col-6">
                    <button type="button" class="btn btn-primary w-100" data-test="copy-join" @click="copyJoin">
                        <i class="ti" :class="copied ? 'ti-check' : 'ti-copy'"></i>{{ copied ? 'Copied' : 'Copy join link' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.ql-grid .btn {
    justify-content: flex-start;
    min-height: 48px;
}
.ql-grid .btn .ti {
    font-size: 1.25rem;
    margin-right: 0.5rem;
}
</style>
