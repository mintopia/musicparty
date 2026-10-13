<template>
    <div class="player p-0 vh-100 w-100 bg-black text-white">
        <iframe class="vh-100 w-100" v-bind:src="youtubeUrl ?? undefined" />
    </div>
</template>
<style></style>
<script lang="ts">
import {defineComponent} from 'vue';

export default defineComponent({
    props: {
        code: {type: String, required: true},
    },
    data() {
        return {
            youtubeUrl: null as string | null,
        };
    },

    methods: {
        playVideo(videoId: string): void {
            console.log(`Play ${videoId}`);
            const cb = Date.now();
            this.youtubeUrl = `https://www.youtube.com/embed/${videoId}?autoplay=1&cb=${cb}`;
        },
    },

    mounted() {
        let channel = `party.${this.code}`;
        console.log('Mounted');
        window.Echo.channel(channel).listen('Party\\PlayYouTubeVideoEvent', (payload: {video: string}) => {
            this.playVideo(payload.video);
        });
    },

    created() {},
});
</script>
