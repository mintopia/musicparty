<template>
    <div class="player p-0 vh-100 w-100 bg-black text-white">
        <iframe class="vh-100 w-100" v-bind:src="youtubeUrl" />
    </div>
</template>
<style>

</style>
<script>
    export default {
        props: [
            'code',
        ],
        data() {
            return {
                'youtubeUrl': null,
            }
        },

        methods: {
            playVideo(videoId) {
                console.log(`Play ${videoId}`);
                const cb = Date.now();
                this.youtubeUrl = `https://www.youtube.com/embed/${videoId}?autoplay=1&cb=${cb}`;
            }
        },

        mounted() {
            let channel = `party.${this.code}`;
            console.log('Mounted');
            window.Echo.channel(channel).listen('Party\\PlayYouTubeVideoEvent', (payload) => {
                this.playVideo(payload.video);
            });
        },

        created() {
        },
    }
</script>
