<script setup lang="ts">
withDefaults(defineProps<{compact?: boolean}>(), {compact: false});
</script>

<template>
    <div
        class="list-group-item placeholder-glow"
        :class="{'py-2': compact}"
        aria-hidden="true"
        data-test="track-placeholder"
    >
        <div class="row align-items-center" :class="compact ? 'g-3' : 'g-2'">
            <div class="col-auto">
                <span class="placeholder rounded d-block cover" :class="{compact}"></span>
            </div>
            <div class="col">
                <div><span class="placeholder col-6"></span></div>
                <div><span class="placeholder placeholder-sm col-4"></span></div>
            </div>
            <div v-if="!compact" class="col-auto"><span class="placeholder" style="width: 2.5rem"></span></div>
            <div class="col-auto text-center" :class="{'w-8': !compact}">
                <span class="placeholder btn btn-primary disabled" :class="compact ? 'action-compact' : 'ms-2 w-100'"></span>
            </div>
        </div>
    </div>
</template>

<style scoped>
.cover {
    width: 60px;
    height: 60px;
}

.cover.compact {
    width: 48px;
    height: 48px;
}

.action-compact {
    width: 72px;
    min-height: 44px;
}

@media (min-width: 768px) {
    .cover.compact {
        width: 44px;
        height: 44px;
    }

    .action-compact {
        width: 112px;
        min-height: 36px;
    }
}
</style>
