<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import {homeCrumbs} from '@/lib/breadcrumbs';
import {Head, Link} from '@inertiajs/vue3';
import PageHead from '@/components/PageHead.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {create as linkAccount, deleteMethod as removeAccount} from '@/routes/linkedaccounts';
import {edit as editProfile} from '@/routes/user/profile';
import {spotify as spotifyLinks} from '@/routes/user';

defineProps<{
    user: {nickname: string; email: string; avatar: string};
    accounts: {
        id: number;
        providerCode: string;
        providerName: string;
        name: string;
        avatarUrl: string | null;
        canDelete: boolean;
    }[];
    availableLinks: {code: string; name: string}[];
}>();
</script>

<template>
    <AppLayout title="Profile">
        <template #breadcrumbs>
            <Breadcrumbs :items="[...homeCrumbs(), {label: 'Profile'}]" />
        </template>
        <Head title="Profile" />
        <div class="mp-narrow">
            <PageHead title="Profile" summary="Your name at parties and the accounts you login with.">
                <Link :href="editProfile().url" class="btn btn-primary">
                    <i class="icon ti ti-edit"></i>
                    Edit profile
                </Link>
            </PageHead>

            <div class="card mb-4">
                <div class="card-body mp-who">
                    <span
                        class="avatar avatar-xl rounded"
                        role="img"
                        aria-label="Your avatar"
                        :style="{backgroundImage: `url('${user.avatar}')`}"
                    ></span>
                    <div>
                        <h2 class="h3">{{ user.nickname }}</h2>
                        <p class="text-secondary mb-0">{{ user.email }}</p>
                    </div>
                </div>
            </div>

            <h2 class="mb-3">Linked accounts</h2>
            <div class="row g-3 mb-3">
                <div v-for="account in accounts" :key="account.id" class="col-md-6">
                    <div class="card mp-link-card h-100">
                        <div class="card-body">
                            <div class="mp-link-head">
                                <span
                                    v-if="account.avatarUrl"
                                    class="avatar avatar-md rounded"
                                    role="img"
                                    :aria-label="`${account.providerName} Avatar`"
                                    :style="{backgroundImage: `url('${account.avatarUrl}')`}"
                                ></span>
                                <span
                                    v-else
                                    class="avatar avatar-md rounded text-white"
                                    :class="`bg-${account.providerCode}`"
                                >
                                    <i :class="`icon ti ti-brand-${account.providerCode}`"></i>
                                </span>
                                <div>
                                    <h3 class="card-title mb-0">{{ account.providerName }}</h3>
                                    <p class="text-secondary">{{ account.name }}</p>
                                </div>
                            </div>
                            <div class="mp-link-state"><span class="status-dot status-green"></span>Linked</div>
                            <Link
                                v-if="account.canDelete"
                                class="btn btn-outline-danger btn-sm"
                                :href="removeAccount(account.id).url"
                            >
                                <i class="icon ti ti-link-off"></i>
                                Unlink
                            </Link>
                            <p v-else class="text-secondary small">
                                Your login. It can't be unlinked while it is your only one.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="btn-list">
                <Link :href="spotifyLinks().url" class="btn btn-outline-success">
                    <i class="icon ti ti-brand-spotify"></i>
                    Manage Spotify links
                </Link>
                <a
                    v-for="provider in availableLinks"
                    :key="provider.code"
                    class="btn btn-outline-secondary"
                    :href="linkAccount(provider.code).url"
                >
                    <i :class="`icon ti ti-brand-${provider.code}`"></i>
                    Link {{ provider.name }}
                </a>
            </div>
        </div>
    </AppLayout>
</template>
