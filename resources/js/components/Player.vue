<template>
    <div>
        <div class="player p-0">
            <div class="blur-bg py-3 border-bottom border-primary text-white" style="background-image: url('https://i.scdn.co/image/ab67616d0000b2732cfa757b31982c21a7d9154c');">
                <div class="container-xl">
                    <div class="col-xl-8 offset-xl-2 col-lg-10 offset-lg-1">
                        <div class="d-flex title flex-fill text-white p-0 pb-3">
                            <div class="flex-grow-1" v-if="state !== null">
                                <h1 class="mb-0">{{ state.name }}</h1>
                            </div>
                            <div class="text-end" v-if="state !== null">
                                <h1 class="mb-0">{{ state.code }}</h1>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-auto">
                                <img class="albumart" src="https://i.scdn.co/image/ab67616d0000b2732cfa757b31982c21a7d9154c" />
                            </div>
                            <div class="col">
                                <div class="fs-2 pb-3 overflow-hidden"><strong>I May Fall</strong></div>
                                <div class="pb-2 overflow-hidden">
                                    <i class="icon ti ti-users"></i>
                                    Jeff Williams, Casey Lee Williams
                                </div>
                                <div class="pb-3 overflow-hidden">
                                    <i class="icon ti ti-vinyl"></i>
                                    RWBY, Vol 1. (Music from the Rooster Teeth Series)
                                </div>
                                <div class="progress">
                                    <div class="progress-bar" role="progressbar" style="width: 55%;"></div>
                                </div>
                                <div class="d-flex">
                                    <div class="flex-grow">
                                        2:32
                                    </div>
                                    <div class="flex-grow-1 text-center">
                                        <a href="#" class="link-underline-opacity-0 link-light"><i class="icon ti ti-player-pause-filled"></i></a>
                                        <a href="#" class="link-underline-opacity-0 link-light"><i class="icon ti ti-player-skip-forward-filled"></i></a>
                                    </div>
                                    <div class="text-end">
                                        4:03
                                    </div>
                                </div>
                            </div>
                        </div>
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
            }
        },

        methods: {
            updateState(state) {
                console.log(state);
                this.state = state;
            },
        },

        mounted() {
            this.updateState(JSON.parse(this.initialstate));
            let channel = `party.${this.code}`;
            window.Echo.private(channel).listen('Party\\UpdatedEvent', (payload) => {
                this.updateState(payload);
            });
        },

        created() {
        },

        beforeDestroy() {
        }
    }
</script>
