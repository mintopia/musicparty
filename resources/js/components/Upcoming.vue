<template>
    <div>
        <div v-if="upcoming.length > 0" class="card">
            <div class="list-group card-list-group">
                <TransitionGroup name="list" tag="div">
                    <div class="list-group-item" v-for="song in upcoming" :key="song.id">
                        <div class="row g-2 align-items-center">
                            <div class="col-auto">
                                <img v-bind:src="song.album.image_url" v-bind:title="song.album.name" class="rounded" width="60" height="60">
                            </div>
                            <div class="col">
                                {{ song.name }}
                                <div class="text-secondary">
                                    {{ combineArtists(song.artists) }}
                                </div>
                                <div class="text-muted">
                                    <template v-if="song.user">Requested by {{ song.user }}</template>
                                    <template v-else>Fallback Track</template>
                                </div>
                            </div>
                            <div class="col-auto text-secondary">{{ formatMs(song.length) }}</div>
                            <div class="col-auto text-center">
                                <div class="row">
                                    <template v-if="can_downvote">
                                        <div class="col-4">
                                            <i v-if="song.vote <= 0" class="icon ti ti-arrow-big-up cursor-pointer" @click="vote(song.id, 1)"></i>
                                            <i v-if="song.vote === 1" class="icon ti ti-arrow-big-up-filled cursor-pointer text-success" @click="vote(song.id, 0)"></i>
                                        </div>
                                        <div class="col-4">
                                            {{ song.score }}
                                        </div>
                                        <div class="col-4">
                                            <i v-if="song.vote >= 0" class="icon ti ti-arrow-big-down cursor-pointer" @click="vote(song.id, -1)"></i>
                                            <i v-if="song.vote === -1" class="icon ti ti-arrow-big-down-filled cursor-pointer text-danger" @click="vote(song.id, 0)"></i>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <div class="col-4">
                                            <i v-if="song.vote <= 0" class="icon ti ti-heart cursor-pointer" @click="vote(song.id, 1)"></i>
                                            <i v-if="song.vote === 1" class="icon ti ti-heart-filled cursor-pointer text-success" @click="vote(song.id, 0)"></i>
                                        </div>
                                        <div class="col-8">
                                            {{ song.score }}
                                        </div>
                                    </template>
                                </div>
                            </div>
                            <div v-if="can_manage" class="col-auto lh-1">
                                <i class="icon ti ti-x cursor-pointer" @click="deleteSong(song.id)"></i>
                            </div>
                        </div>
                    </div>
                </TransitionGroup>
            </div>
        </div>
        <VueEternalLoading :load="getPage">
            <template #loading>
                <div class="text-center text-muted p-2">
                    Loading upcoming songs...
                </div>
            </template>

            <template #no-results>
                <div class="text-center text-muted p-2">
                    There are no upcoming songs
                </div>
            </template>

            <template #no-more>
                <div class="text-center text-muted p-2">
                    There are no more upcoming songs
                </div>
            </template>

            <template #error>
                <div class="text-center text-muted p-2">
                    Error fetching upcoming songs
                </div>
            </template>
        </VueEternalLoading>
    </div>
</template>
<style>

    .list-move,
    .list-enter-active,
    .list-leave-active {
        transition: all 0.5s ease;
    }

    .list-enter-from,
    .list-leave-to {
        opacity: 0;
        transform: translateX(30px);
    }
    .list-leave-active {
        position: absolute;
    }
</style>
<script>
    import { VueEternalLoading } from '@ts-pro/vue-eternal-loading';

    export default {
        components: {
            VueEternalLoading,
        },
        props: [
            'can_manage',
            'can_downvote',
            'party',
            'initialstate',
        ],
        data() {
            return {
                page: 1,
                code: null,
                upcoming: [],
            }
        },

        methods: {
            vote(song_id, value) {
                axios.post(`/api/v1/parties/${this.party}/upcomingsongs/${song_id}/vote`, {
                    vote: value,
                }).then((response) => {
                    this.addOrUpdateItem(response.data.data);
                }).catch((error) => {
                    // Do Nothing
                });
            },

            deleteSong(song_id) {
                this.removeItem(song_id);
                axios.delete(`/api/v1/parties/${this.party}/upcomingsongs/${song_id}`);
            },

            getPage({ loaded }) {
                axios.get(`/api/v1/parties/${this.party}/upcomingsongs`, {
                    params: {
                        page: this.page
                    }
                }).then(response => {
                    if (response.data.data.length > 0) {
                        this.page += 1;
                        response.data.data.forEach((newItem) => {
                            this.addOrUpdateItem(newItem);
                        });
                        this.sortUpcoming();
                    }
                    loaded(response.data.data.length, 20);
                }).catch((error) => {
                    // Do Nothing
                });
            },

            combineArtists(artists) {
                return artists.map(artist => artist.name).join(', ');
            },

            sortUpcoming() {
                this.upcoming.sort((alpha, bravo) => {
                    if (alpha.score === bravo.score) {
                        let diff = Math.sign(Date.parse(alpha.created_at) - Date.parse(bravo.created_at));
                        if (diff === 0) {
                            return Math.sign(alpha.id - bravo.id);
                        }
                        return diff;
                    } else {
                        return Math.sign(bravo.score - alpha.score);
                    }
                });
            },

            addOrUpdateItem(newItem, fromPubSub = false) {
                let result = this.upcoming.some((existing, index) => {
                    if (existing.id === newItem.id) {
                        if (fromPubSub) {
                            newItem.vote = existing.vote;
                        }
                        this.upcoming.splice(index, 1, newItem);
                        return true;
                    }
                    return false;
                });
                if (!result) {
                    this.upcoming.push(newItem);
                }
            },

            removeItem(id) {
                this.upcoming = this.upcoming.filter(song => song.id !== id);
            },

            formatMs(ms) {
                const mins = Math.floor(ms / 60000);
                const seconds = Math.floor((ms % 60000) / 1000).toFixed(0);
                return mins + ':' + (seconds < 10 ? '0' : '') + seconds;
            },
        },

        mounted() {
            Echo.private(`party.${this.party}`).listen('UpcomingSong\\UpdatedEvent', (payload) => {
                this.addOrUpdateItem(payload, true);
                this.sortUpcoming();
            });
            Echo.private(`party.${this.party}`).listen('UpcomingSong\\RemovedEvent', (payload) => {
                this.removeItem(payload.id);
            });
            const initial = JSON.parse(this.initialstate);
            if (initial) {
                initial.forEach((item) => {
                    this.addOrUpdateItem(item);
                });
            }
        },

        created() {
        }
    }
</script>
