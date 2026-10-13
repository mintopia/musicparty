<script setup lang="ts">
defineProps<{user?: {id: number; nickname: string}; active?: boolean}>();
</script>

<template>
    <li class="breadcrumb-item"><a href="/">Home</a></li>
    <li class="breadcrumb-item"><a href="/admin">Admin</a></li>
    <li class="breadcrumb-item" :class="{active: !user}"><a href="/admin/users">Users</a></li>
    <li v-if="user" class="breadcrumb-item" :class="{active}">
        <a :href="`/admin/users/${user.id}`">{{ user.nickname }}</a>
    </li>
    <slot />
</template>
