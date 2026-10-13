<script setup lang="ts">
import {Head, Link} from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import {home} from '@/routes';
import {create as linkAccount, deleteMethod as removeAccount} from '@/routes/linkedaccounts';
import {profile} from '@/routes/user';
import {edit as editProfile} from '@/routes/user/profile';

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
            <li class="breadcrumb-item"><a :href="home().url">Home</a></li>
            <li class="breadcrumb-item active">
                <a :href="profile().url">Profile</a>
            </li>
        </template>
        <Head title="Profile" />
        <div class="row">
            <div class="col-xl-9 col-sm-8">
                <div class="row row-deck mb-4">
                    <div class="col-12 page-header mt-2">
                        <h1>Linked Accounts</h1>
                    </div>
                    <div v-for="account in accounts" :key="account.id" class="col-xl-4 col-sm-6 my-2 mh-100">
                        <div class="card">
                            <div class="ribbon ribbon-top" :class="`bg-${account.providerCode}`">
                                <i :class="`icon ti ti-brand-${account.providerCode}`"></i>
                            </div>
                            <div class="row row-0">
                                <div class="col-auto w-7 d-flex align-items-center">
                                    <img
                                        v-if="account.avatarUrl"
                                        :src="account.avatarUrl"
                                        class="w-100 h-100 mh-100 object-cover card-img-start"
                                        :alt="`${account.providerName} Avatar`"
                                    />
                                    <i
                                        v-else
                                        :class="`icon ti ti-brand-${account.providerCode} icon-lg text-muted m-auto d-block`"
                                        :title="`No ${account.providerName} Avatar`"
                                    ></i>
                                </div>
                                <div class="col">
                                    <div class="card-body p-2">
                                        <h3 class="card-title">{{ account.providerName }}</h3>
                                        <p class="text-secondary">{{ account.name }}</p>
                                        <Link
                                            v-if="account.canDelete"
                                            class="btn btn-sm btn-outline-danger"
                                            :href="removeAccount(account.id).url"
                                            >Remove</Link
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-if="availableLinks.length" class="col-12 btn-list">
                        <a
                            v-for="provider in availableLinks"
                            :key="provider.code"
                            :class="`btn btn-${provider.code}`"
                            :href="linkAccount(provider.code).url"
                        >
                            <i :class="`icon ti ti-brand-${provider.code}`"></i>
                            Link with {{ provider.name }}
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-sm-4">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <span
                                class="avatar avatar-xl rounded"
                                :style="{backgroundImage: `url('${user.avatar}')`}"
                            ></span>
                        </div>
                        <div class="card-title mb-1">{{ user.nickname }}</div>
                        <div class="text-secondary">
                            <span class="text-muted">{{ user.email }}</span>
                        </div>
                    </div>
                    <div class="card-footer d-flex">
                        <Link :href="editProfile().url" class="btn btn-primary ms-auto">
                            <i class="icon ti ti-edit"></i>
                            Edit
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
