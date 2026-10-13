<script setup lang="ts">
defineProps<{party: {code: string; name: string}; active?: boolean}>();
</script>

<template>
    <li class="breadcrumb-item"><a href="/">Home</a></li>
    <li class="breadcrumb-item">
        <a :href="`/parties/${party.code}`">{{ party.name }}</a>
    </li>
    <li v-if="active" class="breadcrumb-item active">
        <a :href="`/parties/${party.code}/moderation`">Moderation</a>
    </li>
    <template v-else>
        <li class="breadcrumb-item">
            <a :href="`/parties/${party.code}/moderation`">Moderation</a>
        </li>
        <slot />
    </template>
</template>
