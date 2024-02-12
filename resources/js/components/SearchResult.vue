<template>
    <div v-if="state" class="list-group-item">
        <div class="row g-2 align-items-center">
            <div class="col-auto">
                <img v-bind:src="state.album.images[0].url" v-bind:title="state.album.name" class="rounded" width="60" height="60">
            </div>
            <div class="col">
                <a v-bind:href="state.external_urls.spotify" title="View on Spotify" class="link-underline-opacity-0" target="_blank">
                    {{ state.name }}
                </a>
                <div class="text-secondary">
                    {{ combineArtists(state.artists) }}
                </div>
                <div class="text-muted">
                    <template v-if="state.upcoming">
                        <template v-if="state.user">
                            Requested by {{ state.user }}
                        </template>
                        <template v-else>
                            Fallback Track
                        </template>
                    </template>
                    <template v-else-if="state.allowed === false">
                        <span class="text-warning">
                            <i class="icon ti ti-alert-triangle"></i>
                            {{ state.reason }}
                        </span>
                    </template>
                    <template v-else>
                        <span class="text-yellow">
                            <template v-for="index in 5">
                                <i v-if="Math.ceil(state.popularity / 20) >= (index - 1)" class="icon ti ti-star-filled" v-bind:title="'Rating: ' + state.popularity + ' / 100'"></i>
                                <i v-else class="icon ti ti-star" v-bind:title="'Rating: ' + state.popularity + ' / 100'"></i>
                            </template>
                        </span>
                    </template>
                </div>
            </div>
            <div class="col-auto text-secondary">
                {{ formatMs(state.duration_ms) }}
            </div>
            <div class="col-auto text-center w-8" v-if="state.upcoming">
                <div class="row">
                    <template v-if="can_downvote == 1">
                        <div class="col-5 text-end">
                            <i v-if="state.vote <= 0" class="icon ti ti-arrow-big-up cursor-pointer" @click="vote(1)"></i>
                            <i v-if="state.vote === 1" class="icon ti ti-arrow-big-up-filled cursor-pointer text-success" @click="vote(0)"></i>
                        </div>
                        <div class="col-3 ps-0 pe-0">
                            {{ state.score }}
                        </div>
                        <div class="col-4 text-start">
                            <i v-if="state.vote >= 0" class="icon ti ti-arrow-big-down cursor-pointer" @click="vote(-1)"></i>
                            <i v-if="state.vote === -1" class="icon ti ti-arrow-big-down-filled cursor-pointer text-danger" @click="vote(0)"></i>
                        </div>
                    </template>
                    <template v-else>
                        <div class="col-7 text-end">
                            <i v-if="state.vote <= 0" class="icon ti ti-heart cursor-pointer" @click="vote(1)"></i>
                            <i v-if="state.vote === 1" class="icon ti ti-heart-filled cursor-pointer text-success" @click="vote(0)"></i>
                        </div>
                        <div class="col-5 text-start">
                            {{ state.score }}
                        </div>
                    </template>
                </div>
            </div>
            <div class="col-auto text-center w-8" v-else>
                <template v-if="state.added">
                    <button class="btn btn-primary ms-2 w-100" >
                        <i class="icon ti ti-music-check"></i>
                        Added
                    </button>
                </template>
                <template v-else-if="!state.allowed">
                    <button class="btn btn-primary ms-2 w-100 disabled">
                        <i class="icon ti ti-music-plus"></i>
                        Add
                    </button>
                </template>
                <template v-else-if="state.sending">
                    <button class="btn btn-primary ms-2 w-100 disabled">
                        <i class="icon ti ti-loader"></i>
                        Add
                    </button>
                </template>
                <template v-else>
                    <button class="btn btn-primary ms-2 w-100" @click="add()">
                        <i class="icon ti ti-music-plus"></i>
                        Add
                    </button>
                </template>
            </div>
        </div>
    </div>
</template>
<style>

</style>
<script>
    export default {
        props: [
            'can_downvote',
            'party',
            'initialstate',
        ],
        data() {
            return {
                state: null,
            }
        },

        methods: {
            add() {
                this.state.sending = true;
                axios.post(`/api/v1/parties/${this.party}/upcomingsongs`, {
                    spotify_id: this.state.id
                }).then((response) => {
                    this.updateResponseFromData(response.data.data);
                }).catch((error) => {
                    // Do Nothing
                }).finally(() => {
                    this.state.sending = false;
                });
            },

            vote(value) {
                axios.post(`/api/v1/parties/${this.party}/upcomingsongs/${this.state.upcoming_id}/vote`, {
                    vote: value,
                }).then((response) => {
                    this.updateResponseFromData(response.data.data);
                }).catch((error) => {
                    // Do Nothing
                });
            },

            updateResponseFromData(data) {
                this.state.user = data.user;
                this.state.score = data.score;
                this.state.vote = data.vote;
                this.state.upcoming = true;
                this.state.upcoming_id = data.id;
            },

            combineArtists(artists) {
                return artists.map(artist => artist.name).join(', ');
            },

            formatMs(ms) {
                const mins = Math.floor(ms / 60000);
                const seconds = Math.floor((ms % 60000) / 1000).toFixed(0);
                return mins + ':' + (seconds < 10 ? '0' : '') + seconds;
            },
        },

        mounted() {
            this.state = JSON.parse(this.initialstate);
        },

        created() {
        }
    }
</script>
