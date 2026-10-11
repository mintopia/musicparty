<script setup>
import {Head} from '@inertiajs/vue3';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import Icon from '../../Components/Icon.vue';
import Button from '../../Components/ui/Button.vue';
import ButtonLink from '../../Components/ui/ButtonLink.vue';
import Card from '../../Components/ui/Card.vue';
import {providerBrands, providerIcons} from '../../lib/providerBrands.js';
import {usePageMeta} from '../../composables/usePageMeta.js';

defineOptions({layout: AdminLayout});

defineProps({
    profile: {type: Object, required: true},
    accounts: {type: Array, required: true},
    available_links: {type: Array, default: () => []},
});

usePageMeta(() => ({
    title: 'Linked Accounts',
    crumbs: [{label: 'Home', href: '/'}, {label: 'Profile'}],
}));

const brandClass = (code) => providerBrands[code] ?? 'bg-primary';
const iconName = (code) => (providerIcons.includes(code) ? code : 'login');
</script>

<template>
    <Head title="Profile" />
    <div class="grid items-start gap-6 lg:grid-cols-[1fr_18rem]">
        <div>
            <p v-if="!accounts.length" class="text-sm text-muted">No linked accounts.</p>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <div v-for="account in accounts" :key="account.id" class="relative" :data-testid="`account-${account.id}`">
                    <Card flush>
                        <span
                            class="absolute right-3 top-0 z-10 flex h-8 w-8 items-center justify-center rounded-b text-white"
                            :class="brandClass(account.provider_code)"
                            aria-hidden="true"
                        >
                            <Icon :name="iconName(account.provider_code)" />
                        </span>
                        <div class="flex">
                            <div class="flex w-24 shrink-0 items-center justify-center bg-background">
                                <img v-if="account.avatar_url" :src="account.avatar_url" :alt="`${account.provider} Avatar`" class="h-full w-full object-cover" />
                                <span v-else class="text-muted [&>svg]:h-8 [&>svg]:w-8" :title="`No ${account.provider} Avatar`">
                                    <Icon :name="iconName(account.provider_code)" />
                                </span>
                            </div>
                            <div class="min-w-0 flex-1 p-3">
                                <h3 class="pr-8 text-base font-semibold">{{ account.provider }}</h3>
                                <p class="mb-3 truncate text-sm text-muted">{{ account.name }}</p>
                                <ButtonLink
                                    v-if="account.can_delete"
                                    :href="`/profile/accounts/${account.id}/delete`"
                                    variant="outline-danger"
                                    data-action="remove-account"
                                >
                                    <template #icon><Icon name="trash" /></template>
                                    Remove
                                </ButtonLink>
                                <Button v-else variant="outline-danger" disabled data-action="remove-account">
                                    <template #icon><Icon name="trash" /></template>
                                    Remove
                                </Button>
                            </div>
                        </div>
                    </Card>
                </div>
            </div>
            <div v-if="available_links.length" class="mt-4 flex flex-wrap gap-2">
                <a
                    v-for="link in available_links"
                    :key="link.code"
                    :href="link.url"
                    class="inline-flex items-center justify-center gap-2 rounded px-4 py-2 text-sm font-medium text-white transition hover:brightness-110"
                    :class="brandClass(link.code)"
                    :data-link="link.code"
                >
                    <Icon :name="iconName(link.code)" />
                    Link with {{ link.name }}
                </a>
            </div>
        </div>
        <Card>
            <div class="text-center">
                <img :src="profile.avatar_url" alt="" class="mx-auto mb-3 h-20 w-20 rounded object-cover" data-testid="profile-avatar" />
                <div class="text-base font-semibold">{{ profile.nickname }}</div>
                <div class="text-sm text-muted">{{ profile.email }}</div>
            </div>
            <template #footer>
                <ButtonLink href="/profile/edit" data-action="edit">
                    <template #icon><Icon name="edit" /></template>
                    Edit
                </ButtonLink>
            </template>
        </Card>
    </div>
</template>
