<script setup>
defineProps({
    member: {type: Object, required: true},
    trust: {type: Number, default: null},
});
</script>

<template>
    <td class="px-5 py-3 text-muted" data-testid="member-trust">{{ trust === null ? '-' : trust.toFixed(2) }}</td>
</template>
