<template>
    <div>
        <div class="row blur-bg p-2 pt-0 vh-100 justify-content-md-center" v-bind:style="getBackgroundImageStyle((state !== null && state.now) ? state.now.album.image_url : null)">
            <div class="d-flex title flex-fill text-white p-0 pb-3">
                <div class="flex-grow-1">
                    <a v-bind:href="partyUri()" class="link-light">{{ name }}</a>
                </div>
                <div class="text-end">
                    {{ code }}
                </div>
            </div>
            <template v-if="state !== null">
                <template v-if="state.now !== null">
                    <div class="d-flex col-10 py-5">
                        <div class="p-2">
                            <img v-bind:src="state.now.album.image_url" v-bind:title="state.now.album.name" class="img-fluid player-album float-start me-3 shadow-lg" />
                        </div>
                        <div class="flex-grow-1">
                            <div class="row">
                                <h2>{{ state.now.name }}</h2>
                            </div>
                            <div class="row p-2">
                                <p class="p-0 ps-2 mt-4 lh-base">
                                    <i class="bi bi-person-circle"></i>
                                    {{ combineArtists(state.now.artists) }}
                                </p>
                                <p class="p-0 ps-2 mb-2 lh-base">
                                    <i class="bi bi-vinyl"></i>
                                    {{ state.now.album.name }}
                                </p>
                            </div>
                            <div class="progress mt-5" style="height: 5px;">
                                <div class="progress-bar" role="progressbar" v-bind:style="`width: ${progress}%;`" v-bind:aria-valuenow="progress" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <div class="d-flex">
                                <div class="flex-grow-1">
                                    {{ currentTime }}
                                </div>
                                <div class="text-end">
                                    {{  endTime }}
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
                <template v-else>
                    Nothing Playing
                </template>
            </template>
            <template v-else>
                Loading
            </template>
        </div>
    </div>
</template>
<style>
.title {
    font-weight: bold;
    font-size: 2em;
}

a {
    text-decoration: none;
}

.blur-bg {
    backdrop-filter: blur(2px);
    background-repeat: no-repeat;
    background-size: 100%;
    background-position: center center;
    background-blend-mode: darken;
    background-color: rgba(0, 0, 0, 0.9);
}

.player-album {
    height: 20em;
    width: auto;
}

.player, .player-next {
    color: #fff;
}

h2 {
    font-size: 3em;
}

p {
    font-size: 1.4em;
    line-height: 2em !important;
}
</style>
<script>
    export default {
        props: [
            'name',
            'code',
            'canmanage',
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
            partyUri() {
                return `/parties/${this.code}`;
            },

            isPlaying() {
                if (this.state === null || this.state.status === null || this.state.status.is_playing === false) {
                    return false;
                }
                return true;
            },

            formatMs(ms) {
                const mins = Math.floor(ms / 60000);
                const seconds = Math.floor((ms % 60000) / 1000).toFixed(0);
                return mins + ':' + (seconds < 10 ? '0' : '') + seconds;
            },

            getBackgroundImageStyle(url) {
                if (url === null) {
                    return '';
                }
                return `background-image: url('${url}');`;
            },

            updateProgressBar() {
                if (this.isPlaying()) {
                    this.startedAt = new Date(this.state.status.updated_at).getTime() - this.state.status.progress;
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

            updateState(state) {
                this.state = state;
                this.updateProgressBar();
            },
        },

        mounted() {
            this.updateState(JSON.parse(this.initialstate));
            let channel = `party.${this.code}`;
            Echo.private(channel).listen('Party\\UpdatedEvent', (payload) => {
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
