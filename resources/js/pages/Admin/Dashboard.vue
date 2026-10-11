<script setup>
import {Head, Link} from '@inertiajs/vue3';
import {computed} from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import Icon from '../../Components/Icon.vue';
import Card from '../../Components/ui/Card.vue';
import {usePageMeta} from '../../composables/usePageMeta.js';

defineOptions({layout: AdminLayout});

const props = defineProps({stats: {type: Object, required: true}});

usePageMeta({title: 'Dashboard', crumbs: [{label: 'Home', href: '/'}, {label: 'Admin', href: '/admin'}, {label: 'Dashboard'}]});

const cards = computed(() => [
    {
        key: 'users',
        href: '/admin/users',
        icon: 'users',
        tile: 'bg-primary',
        total: props.stats.users.total,
        label: 'Users',
        subline: `${props.stats.users.lastWeek} in the last week`,
    },
    {
        key: 'parties',
        href: '/admin/parties',
        icon: 'playlist',
        tile: 'bg-accent',
        total: props.stats.parties.total,
        label: 'Parties',
        subline: `${props.stats.parties.live} live`,
    },
]);
</script>

<template>
    <Head title="Admin" />
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Link v-for="card in cards" :key="card.key" :href="card.href" :data-testid="`card-${card.key}`" class="block">
            <Card>
                <div class="flex items-center gap-4">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded text-white" :class="card.tile">
                        <Icon :name="card.icon" class="!h-6 !w-6" />
                    </span>
                    <div>
                        <p class="font-bold">{{ card.total }} {{ card.label }}</p>
                        <p class="text-sm text-muted">{{ card.subline }}</p>
                    </div>
                </div>
            </Card>
        </Link>
    </div>
</template>
