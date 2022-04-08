<template>
    <div>
        <TransitionGroup name="list" tag="div">
            <div class="row striped-bg" v-for="song in upcoming" :key="song.id">
                <div class="d-flex flex-fill p-2">
                    <div class="flex-grow-1">
                        <img v-bind:src="song.album.image_url" v-bind:title="song.album.name" class="img-fluid party-album float-start me-3" />
                        <span>{{ song.name }}</span>
                        <br />
                        <small>
                            <span class="text-truncate" style="max-width: 70vw;">{{ combineArtists(song.artists) }}</span>
                            &middot;
                            <span v-if="song.votes > 1">{{ song.votes }} votes</span>
                            <span v-else-if="song.votes === 1">1 vote</span>
                            <span v-else>Fallback Track</span>
                        </small>
                    </div>
                    <div class="text-end fs-4 fw-bold pt-2">
                        <i v-if="can_delete" class="bi bi-x-lg me-2" @click="deleteSong(song.id)"></i>
                        <i v-if="song.voted" class="bi bi-heart-fill"></i>
                        <i v-else class="bi bi-heart" @click="vote(song.id)"></i>
                    </div>
                </div>
            </div>
        </TransitionGroup>
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
                    There are no upcoming songs
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
    .bi-heart {
        cursor: pointer;
    }

    .bi-x-lg {
        cursor: pointer;
    }

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
    import { VueEternalLoading, LoadAction } from '@ts-pro/vue-eternal-loading';

    export default {
        components: {
            VueEternalLoading,
        },
        props: [
            'can_delete',
            'party',
        ],
        data() {
            return {
                page: 1,
                upcoming: [],
            }
        },

        methods: {
            vote(song_id) {
                this.upcoming.some((song, index) => {
                    if (song.id === song_id) {
                        if (!song.voted) {
                            song.voted = true;
                            song.votes++;
                        }
                        this.upcoming.splice(index, 1, song);
                        return true;
                    }
                    return false;
                });
                this.sortUpcoming();
                axios.post(`/api/v1/parties/${this.party}/upcoming/${song_id}/vote`);
            },

            deleteSong(song_id) {
                this.removeItem(song_id);
                axios.delete(`/api/v1/parties/${this.party}/upcoming/${song_id}`);
            },

            getPage({ loaded }) {
                axios.get(`/api/v1/parties/${this.party}/upcoming`, {
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
                });
            },

            combineArtists(artists) {
                return artists.map(artist => artist.name).join(', ');
            },

            sortUpcoming() {
                this.upcoming.sort((alpha, bravo) => {
                    if (alpha.votes == bravo.votes) {
                        let diff = Math.sign(Date.parse(alpha.created_at) - Date.parse(bravo.created_at));
                        if (diff === 0) {
                            return Math.sign(alpha.id - bravo.id);
                        }
                        return diff;
                    } else {
                        return Math.sign(bravo.votes - alpha.votes);
                    }
                });
            },

            addOrUpdateItem(newItem) {
                let result = this.upcoming.some((existing, index) => {
                    if (existing.id === newItem.id) {
                        if (newItem.voted === undefined) {
                            newItem.voted = existing.voted;
                        }
                        this.upcoming.splice(index, 1, newItem);
                        return true;
                    }
                    return false;
                });
                if (!result) {
                    if (newItem.voted === undefined) {
                        newItem.voted = false;
                    }
                    this.upcoming.push(newItem);
                }
            },

            removeItem(id) {
                this.upcoming = this.upcoming.filter(song => song.id !== id);
            }
        },

        mounted() {
            Echo.private(`party.${this.party}`).listen('UpcomingSong\\UpdatedEvent', (payload) => {
                this.addOrUpdateItem(payload);
                this.sortUpcoming();
            });
            Echo.private(`party.${this.party}`).listen('UpcomingSong\\RemovedEvent', (payload) => {
                this.removeItem(payload.id);
            });
        },

        created() {
        }
    }
</script>
