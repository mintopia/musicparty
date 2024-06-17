<template>
    <div class="player p-0 vh-100 w-100 bg-black text-white">
        <div class="blur-bg p-3 vh-100 w-100" v-bind:style="getBackgroundImageStyle()">
            <h1 class="d-flex mb-5">
                <div class="col">
                    <template v-if="state !== null">{{ state.name }}</template>
                </div>
                <div class="col-auto text-end">
                    {{ code }}
                </div>
            </h1>
            <div class="container-xl-fluid d-flex">
                <div class="col-xl-8 offset-xl-2 mt-auto">
                    <template v-if="state && state.now !== null">
                        <div class="row">
                            <div class="col-auto">
                                <img class="albumart" v-bind:src="state.now.album.image_url" />
                            </div>
                            <div class="col fs-1 pt-4">
                                <div class="fs-0 pb-3 overflow-hidden"><strong>{{ state.now.name }}</strong></div>
                                <div class="pb-2 overflow-hidden">
                                    <i class="icon-lg ti ti-users fs-1"></i>
                                    {{ combineArtists(state.now.artists) }}
                                </div>
                                <div class="pb-2 overflow-hidden">
                                    <i class="icon-lg ti ti-vinyl fs-1"></i>
                                    {{ state.now.album.name }}
                                </div>
                                <div class="pb-3 overflow-hidden" v-if="state.current && state.current.spotify_id === state.now.spotify_id">
                                    <i class="icon-lg ti ti-music-question fs-1"></i>
                                    <template v-if="state.current.user !== null">
                                        Requested by {{ state.current.user }}
                                    </template>
                                    <template v-else>
                                        Fallback Track
                                    </template>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar smooth-progress-bar" role="progressbar" v-bind:style="`width: ${progress}%;`"></div>
                                </div>
                                <div class="d-flex">
                                    <div class="col">
                                        {{ currentTime }}
                                    </div>
                                    <div class="col text-end">
                                        {{  endTime }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <h2 v-if="state.next !== null" class="mt-4">Next</h2>
                        <div v-if="state.next !== null" class="row text-muted">
                            <div class="col-auto">
                                <img class="albumart-small" v-bind:src="state.next.album.image_url" />
                            </div>
                            <div class="col fs-1 pt-4">
                                <div class="fs-0 pb-3 overflow-hidden"><strong>{{ state.next.name }}</strong></div>
                                <div class="pb-2 overflow-hidden">
                                    <i class="icon-lg ti ti-users fs-1"></i>
                                    {{ combineArtists(state.next.artists) }}
                                </div>
                                <div class="pb-2 overflow-hidden">
                                    <i class="icon-lg ti ti-vinyl fs-1"></i>
                                    {{ state.next.album.name }}
                                </div>
                                <div class="pb-3 overflow-hidden">
                                    <i class="icon-lg ti ti-music-question fs-1"></i>
                                    <template v-if="state.next.user !== null">
                                        Requested by {{ state.next.user }}
                                    </template>
                                    <template v-else>
                                        Fallback Track
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                    <template v-else>
                        <div class="empty text-muted fs-0">
                            <i class="icon icon-lg ti ti-music-off"></i>
                            There is nothing playing
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>
<style>
    .player {
        overflow: hidden;
        padding: 1em;
    }

    .blur-bg {
        backdrop-filter: blur(2px);
        background-repeat: no-repeat;
        background-size: 100%;
        background-position: center center;
        background-blend-mode: darken;
        background-color: rgba(0, 0, 0, 0.9);
    }

    img.albumart {
        max-width: 20em;
    }

    img.albumart-small {
        max-width: 13em;
    }

    .progress {
        height: 5px;
        margin-bottom: 5px;
    }

    .smooth-progress-bar {
        transition: width 1s linear;
    }

    .fs-0 {
        font-size: 2rem !important;
    }
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
                this.updateProgressBar();
            },

            formatMs(ms) {
                const mins = Math.floor(ms / 60000);
                const seconds = Math.floor((ms % 60000) / 1000).toFixed(0);
                return mins + ':' + (seconds < 10 ? '0' : '') + seconds;
            },

            isPlaying() {
                if (this.state === null || this.state.status === null || this.state.status.isPlaying === false) {
                    return false;
                }
                return true;
            },

            getBackgroundImageStyle() {
                if (this.state === null || this.state.now === null) {
                    return '';
                }
                return `background-image: url('${this.state.now.album.image_url}');`;
            },

            updateProgressBar() {
                if (this.isPlaying()) {
                    this.startedAt = new Date(this.state.status.updatedAt).getTime() - this.state.status.progress;
                    this.endTime = this.formatMs(this.state.status.length);
                    this.moveProgressBar();
                    if (this.intervalId === null) {
                        this.intervalId = setInterval(this.moveProgressBar, 1000);
                    }
                } else {
                    this.progress = 0;
                    this.currentTime = '';
                    this.endTime = '';
                    this.startedAt = null;
                    if (this.intervalId !== null) {
                        clearInterval(this.intervalId);
                    }
                }
            },

            moveProgressBar() {
                if (!this.isPlaying() || this.startedAt === null) {
                    return;
                }

                const progress = new Date().getTime() - this.startedAt;
                this.progress = Math.min((progress / this.state.status.length) * 100, 100);
                this.currentTime = this.formatMs(Math.min(progress, this.state.status.length));
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
            if (this.intervalId !== null) {
                clearInterval(this.intervalId);
            }
        }
    }
</script>
