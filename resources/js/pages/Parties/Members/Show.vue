<script setup lang="ts">
import {Head, Link, router} from '@inertiajs/vue3';
import {reactive} from 'vue';
import Pagination from '@/components/Pagination.vue';
import PartyPlayer from '@/components/PartyPlayer.vue';
import SearchSelectField from '@/components/SearchSelectField.vue';
import SearchTextField from '@/components/SearchTextField.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {home} from '@/routes';
import {show} from '@/routes/parties';
import type {LegacyPartyState, Paginator, When} from '@/types';

interface VoteRow {
    id: number;
    songName: string;
    albumName: string;
    imageUrl: string;
    artists: string[];
    url: string;
    value: number;
    createdAt: When | null;
}

const props = defineProps<{
    party: {code: string; name: string; canManage: boolean};
    playerState: LegacyPartyState;
    member: {id: number; nickname: string; role: string; roleCode: string; createdAt: When | null};
    filters: {name?: string | null; type?: string | null; per_page?: number | string};
    types: {code: string; name: string}[];
    votes: Paginator<VoteRow>;
}>();

const form = reactive({name: props.filters.name ?? '', type: props.filters.type ?? ''});
const usersUrl = `/parties/${props.party.code}/users`;
const memberUrl = `${usersUrl}/${props.member.id}`;

function submit(): void {
    const query: Record<string, string | number> = {};
    if (props.filters.per_page !== undefined) {
        query.per_page = props.filters.per_page;
    }
    if (form.name) {
        query.name = form.name;
    }
    if (form.type) {
        query.type = form.type;
    }
    router.get(memberUrl, query);
}
</script>

<template>
    <AppLayout :title="`${member.nickname} - ${party.name}`">
        <template #breadcrumbs>
            <li class="breadcrumb-item"><a :href="home().url">Home</a></li>
            <li class="breadcrumb-item">
                <a :href="show(party.code).url">{{ party.name }}</a>
            </li>
            <li class="breadcrumb-item">
                <a :href="usersUrl">Users</a>
            </li>
            <li class="breadcrumb-item active">
                <a :href="memberUrl">{{ member.nickname }}</a>
            </li>
        </template>
        <template #header>
            <PartyPlayer :code="party.code" :initial-state="playerState" />
        </template>
        <Head :title="`${member.nickname} - ${party.name}`" />
        <div class="page-header mt-0">
            <h1>{{ member.nickname }}</h1>
        </div>

        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="datagrid">
                            <div>
                                <div class="datagrid-title">Nickname</div>
                                <div>{{ member.nickname }}</div>
                            </div>
                            <div>
                                <div class="datagrid-title">Role</div>
                                <div>{{ member.role }}</div>
                            </div>
                            <div>
                                <div class="datagrid-title">Joined Party</div>
                                <div>
                                    <span v-if="member.createdAt" :title="member.createdAt.iso">{{
                                        member.createdAt.human
                                    }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer align-content-end d-flex btn-list">
                        <Link :href="`${memberUrl}/edit`" class="ms-auto btn btn-primary">
                            <i class="icon ti ti-edit"></i>
                            Edit
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <h2>Votes</h2>

        <div class="row">
            <div class="col-md-12 col-lg-3 mb-4">
                <form :action="memberUrl" method="get" class="card" @submit.prevent="submit">
                    <div class="card-header">
                        <h3 class="card-title">Search</h3>
                    </div>
                    <div class="card-body">
                        <SearchTextField v-model="form.name" name="Song Name" property="name" />
                        <SearchSelectField
                            v-model="form.type"
                            name="Type"
                            property="type"
                            :options="types"
                            value-key="code"
                            label-key="name"
                        />
                    </div>
                    <div class="card-footer d-flex">
                        <button class="btn btn-primary ms-auto" type="submit">Search</button>
                    </div>
                </form>
            </div>

            <div class="col-lg-9 col-md-12">
                <div class="card">
                    <div class="table-responsive">
                        <table class="table table-hover table-vcenter text-nowrap card-table">
                            <thead>
                                <tr>
                                    <th>Song</th>
                                    <th class="text-center">Vote</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="vote in votes.data" :key="vote.id" data-test="vote-row">
                                    <td>
                                        <div class="d-flex">
                                            <div class="col-auto">
                                                <img
                                                    :src="vote.imageUrl"
                                                    height="40"
                                                    width="40"
                                                    :title="vote.albumName"
                                                />
                                            </div>
                                            <div class="col ps-2 text-wrap">
                                                <Link :href="vote.url">{{ vote.songName }}</Link>
                                                <div class="text-secondary text-wrap">
                                                    {{ vote.artists.join(', ') }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <i
                                            v-if="vote.value > 0"
                                            class="icon ti ti-arrow-big-up-filled text-success"
                                            data-test="vote-up"
                                        ></i>
                                        <i
                                            v-else
                                            class="icon ti ti-arrow-big-down-filled text-danger"
                                            data-test="vote-down"
                                        ></i>
                                    </td>
                                    <td>
                                        <span v-if="vote.createdAt" :title="vote.createdAt.iso">{{
                                            vote.createdAt.human
                                        }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-if="votes.last_page > 1" class="card-footer d-flex">
                        <Pagination class="ms-auto" :paginator="votes" />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
