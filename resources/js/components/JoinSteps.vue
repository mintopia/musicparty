<script setup lang="ts">
defineProps<{current: 1 | 2}>();
</script>

<template>
    <ol class="mp-steps mb-4" aria-label="Join steps" data-test="join-steps">
        <li :class="{'is-done': current > 1}" :aria-current="current === 1 ? 'step' : undefined">Login</li>
        <li :aria-current="current === 2 ? 'step' : undefined">Nickname</li>
        <li>In the party</li>
    </ol>
</template>
