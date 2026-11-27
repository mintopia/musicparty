<script setup lang="ts">
import {computed} from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import {homeCrumbs} from '@/lib/breadcrumbs';
import {Head} from '@inertiajs/vue3';
import EmptyState from '@/components/EmptyState.vue';
import ModPanel from '@/components/ModPanel.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {adminModUrl, adminModUsersUrl, groupMods} from '@/lib/mods';
import type {AdminMod} from '@/types';

const props = defineProps<{mods: AdminMod[]}>();

const groups = computed(() => groupMods(props.mods));
</script>

<template>
    <AppLayout title="Mod settings">
        <template #breadcrumbs>
            <Breadcrumbs :items="[...homeCrumbs(), {label: 'Mods'}]" />
        </template>
        <Head title="Mod settings" />
        <div class="col-xl-8 offset-xl-2 col-lg-10 offset-lg-1">
            <div class="page-header mt-0">
                <div>
                    <h1>Mods</h1>
                    <p class="text-secondary mb-0">
                        Installed on this instance. Turn a mod on here to let parties use it.
                    </p>
                </div>
            </div>
            <EmptyState
                v-if="mods.length === 0"
                icon="ti-puzzle-off"
                title="No mods available"
                description="No mods are installed on this instance."
            />
            <div v-else>
                <section v-for="group in groups" :key="group.name" class="mod-group" :data-group="group.name">
                    <h2 class="mod-group-heading">
                        {{ group.name }} <span class="mod-group-count">{{ group.mods.length }}</span>
                    </h2>
                    <ModPanel
                        v-for="mod in group.mods"
                        :key="mod.code"
                        :mod="mod"
                        :url="adminModUrl(mod.code)"
                        :user-search-url="adminModUsersUrl()"
                    />
                </section>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.mod-group + .mod-group {
    margin-top: 2rem;
}

.mod-group-heading {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0;
    font-size: 1.125rem;
    font-weight: 700;
}

.mod-group-count {
    padding: 0 0.5rem;
    border-radius: 999px;
    background: var(--tblr-bg-surface-secondary);
    color: var(--tblr-secondary);
    font-size: 0.75rem;
    font-weight: 600;
}
</style>
