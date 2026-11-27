<script setup lang="ts">
import {Head} from '@inertiajs/vue3';
import EmptyState from '@/components/EmptyState.vue';
import ModPanel from '@/components/ModPanel.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {adminModUrl, adminModUsersUrl, adminModsUrl} from '@/lib/mods';
import type {AdminMod} from '@/types';

defineProps<{mods: AdminMod[]}>();
</script>

<template>
    <AppLayout title="Mod settings">
        <template #breadcrumbs>
            <li class="breadcrumb-item"><a :href="'/'">Home</a></li>
            <li class="breadcrumb-item active"><a :href="adminModsUrl()">Mods</a></li>
        </template>
        <Head title="Mod settings" />
        <div class="col-xl-8 offset-xl-2 col-lg-10 offset-lg-1">
            <div class="page-header mt-0">
                <h1>Mod settings</h1>
            </div>
            <EmptyState
                v-if="mods.length === 0"
                icon="ti-puzzle-off"
                title="No mods available"
                description="No mods are installed on this instance."
            />
            <div v-else class="accordion">
                <ModPanel
                    v-for="mod in mods"
                    :key="mod.code"
                    :mod="mod"
                    :url="adminModUrl(mod.code)"
                    :user-search-url="adminModUsersUrl()"
                />
            </div>
        </div>
    </AppLayout>
</template>
