<template>
    <div>
        <template v-if="this.state !== null && this.state.next">


            <h3>Up Next</h3>

            <div class="card mb-3">
                <div class="list-group card-list-group">
                    <div class="list-group-item">
                        <div class="row g-2 align-items-center">
                            <div class="col-auto">
                                <img v-bind:src="state.next.album.image_url" class="rounded" v-bind:title="state.next.album.name" width="60" height="60">
                            </div>
                            <div class="col">
                                {{ state.next.name }}
                                <div class="text-secondary">
                                    {{ combineArtists(state.next.artists) }}
                                </div>
                                <div class="text-muted">
                                    <template v-if="state.next.user">Requested by {{ state.next.user }}</template>
                                    <template v-else>Fallback Track</template>
                                </div>
                            </div>
                            <div class="col-auto text-secondary text-center">
                                {{formatMs(state.next.length) }}
                                <div class="text-muted mt-2">
                                    <template v-if="state.next.score === 1 || state.next.score === -1">{{ state.next.score }} vote</template>
                                    <template v-else>{{ state.next.score }} votes</template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
<style>
</style>
<script>
    export default {
        props: [
            'code',
            'initialstate',
        ],
        data() {
            return {
                state: null,
                currentTime: '',
                endTime: '',
                progress: '',
                startedAt: null,
                intervalId: null,
            }
        },

        methods: {
            updateState(state) {
                this.state = state;
            },

            formatMs(ms) {
                const mins = Math.floor(ms / 60000);
                const seconds = Math.floor((ms % 60000) / 1000).toFixed(0);
                return mins + ':' + (seconds < 10 ? '0' : '') + seconds;
            },

            combineArtists(artists) {
                return artists.map(artist => artist.name).join(', ');
            },
        },

        mounted() {
            this.updateState(JSON.parse(this.initialstate));
            let channel = `party.${this.code}`;
            window.Echo.channel(channel).listen('Party\\UpdatedEvent', (payload) => {
                this.updateState(payload);
            });
        },

        created() {
        },

        beforeDestroy() {
        }
    }
</script>
