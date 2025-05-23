<template>
    <div>
        <div class="player p-0">
            <div class="blur-bg py-3 border-bottom border-primary text-white" v-bind:style="getBackgroundImageStyle()">
                <div class="container-xl">
                    <div class="col-xl-8 offset-xl-2 col-lg-10 offset-lg-1">
                        <template v-if="state && state.now !== null">
                            <div class="row">
                                <div class="col-auto">
                                    <img class="albumart" v-bind:src="state.now.album.image_url" />
                                </div>
                                <div class="col">
                                    <div class="fs-2 pb-3 overflow-hidden"><strong>{{ state.now.name }}</strong></div>
                                    <div class="pb-2 overflow-hidden">
                                        <i class="icon ti ti-users"></i>
                                        {{ combineArtists(state.now.artists) }}
                                    </div>
                                    <div class="pb-2 overflow-hidden">
                                        <i class="icon ti ti-vinyl"></i>
                                        {{ state.now.album.name }}
                                    </div>
                                    <div class="pb-3 overflow-hidden" v-if="state.current && state.current.spotify_id === state.now.spotify_id">
                                        <i class="icon ti ti-music-question"></i>
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
                                        <div class="w-25">
                                            {{ currentTime }}
                                        </div>
                                        <div class="w-50 text-center">
                                            <!--
                                            <i @click="control('previous')" v-if="can_manage" class="icon ti ti-player-skip-back-filled cursor-pointer me-2"></i>
                                            <i @click="control('pause')" v-if="state.status.isPlaying && can_manage" class="icon ti ti-player-pause-filled cursor-pointer me-2"></i>
                                            <i @click="control('play')" v-if="!state.status.isPlaying && can_manage" class="icon ti ti-player-play-filled cursor-pointer me-2"></i>
                                            <i @click="control('next')" v-if="can_manage" class="icon ti ti-player-skip-forward-filled cursor-pointer"></i>
                                            -->
                                        </div>
                                        <div class="w-25 text-end">
                                            {{  endTime }}
                                        </div>
                                    </div>
                                    <div class="mt-3 text-center">
                                        <i class="icon ti ti-thumb-down cursor-pointer d-inline-block mr-4" @click="rate(-1)"></i>
                                        <span class="d-inline-block mx-2">{{ state.current.rating }}</span>
                                        <i class="icon ti ti-thumb-up cursor-pointer d-inline-block ml-4" @click="rate(1)"></i>
                                    </div>
                                </div>
                            </div>
                        </template>
                        <template v-else>
                            <div class="empty text-muted">
                                <i class="icon icon-lg ti ti-music-off"></i>
                                There is nothing playing
                            </div>
                        </template>
                    </div>
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
        max-width: 11em;
    }

    .progress {
        height: 5px;
        margin-bottom: 5px;
    }

    .smooth-progress-bar {
        transition: width 1s linear;
    }
</style>
<script>
    export default {
        props: [
            'code',
            'initialstate',
            'can_manage',
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

            control(action) {
                axios.post(`/api/v1/parties/${this.code}/control`, {
                    action: action,
                }).then((response) => {
                    this.updateState(response.data.data.status);
                });
            },

            rate(value) {
                axios.post(`/api/v1/parties/${this.code}/playedsongs/${this.state.current.id}/rate`, {
                    rating: value
                });
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
