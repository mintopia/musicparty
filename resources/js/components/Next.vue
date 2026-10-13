<template>
    <div>
        <template v-if="state !== null && state.next">


            <h3>Up Next</h3>

            <div class="card mb-3">
                <div class="list-group card-list-group">
                    <div class="list-group-item">
                        <div v-bind:class="'row g-2 align-items-center ' + state.next.css_classes">
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
                                    <template v-else>{{ state.next.fallback_name }}</template>
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
.whamageddon {
    padding-top: 20px;
}

.whamageddon::before {
    content: 'Whamageddon!';
    white-space:nowrap;
    position: absolute;
    padding: 5px 100px;
    width: 100%;
    color: white;
    text-align: center;
    text-transform: uppercase;
    font-size: 10px;
    font-weight: bold;
    box-sizing: border-box;
    top: 0;
    left: 0;
    background-color: #990000;
}
</style>
<script lang="ts">
    import {defineComponent} from 'vue';
    import type {LegacyArtist, LegacyPartyState} from '../types';

    export default defineComponent({
        props: {
            code: {type: String, required: true},
            initialstate: {type: String, required: true},
        },
        data() {
            return {
                state: null as LegacyPartyState | null,
                currentTime: '',
                endTime: '',
                progress: '',
                startedAt: null as number | null,
                intervalId: null as ReturnType<typeof setInterval> | null,
            }
        },

        methods: {
            updateState(state: LegacyPartyState): void {
                this.state = state;
            },

            formatMs(ms: number): string {
                const mins = Math.floor(ms / 60000);
                const seconds = Math.floor((ms % 60000) / 1000).toFixed(0);
                return mins + ':' + (Number(seconds) < 10 ? '0' : '') + seconds;
            },

            combineArtists(artists: LegacyArtist[]): string {
                return artists.map(artist => artist.name).join(', ');
            },
        },

        mounted() {
            this.updateState(JSON.parse(this.initialstate));
            let channel = `party.${this.code}`;
            window.Echo.channel(channel).listen('Party\\UpdatedEvent', (payload: LegacyPartyState) => {
                this.updateState(payload);
            });
        },

        created() {
        },

        beforeDestroy() {
        }
    })
</script>
