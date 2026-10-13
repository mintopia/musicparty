<template>
    <div>
        <div v-if="upcoming.length > 0" class="card">
            <div class="list-group card-list-group">
                <TransitionGroup name="list" tag="div">
                    <div class="list-group-item" v-for="song in upcoming" :key="song.id">
                        <div v-bind:class="'row g-2 align-items-center ' + song.css_classes">
                            <div class="col-auto">
                                <img
                                    v-bind:src="song.album.image_url"
                                    v-bind:title="song.album.name"
                                    class="rounded"
                                    width="60"
                                    height="60"
                                />
                            </div>
                            <div class="col">
                                {{ song.name }}
                                <div class="text-secondary">
                                    {{ combineArtists(song.artists) }}
                                </div>
                                <div v-if="song.scheduled_for" class="text-info scheduled-time">
                                    <i class="icon ti ti-clock"></i>
                                    Scheduled for {{ formatScheduled(song.scheduled_for) }}
                                </div>
                                <div class="text-muted">
                                    <template v-if="song.user">Requested by {{ song.user }}</template>
                                    <template v-else>{{ song.fallback_name }}</template>
                                </div>
                            </div>
                            <div class="col-auto text-secondary song-length">{{ formatMs(song.length) }}</div>
                            <div class="col-auto text-center">
                                <div class="row">
                                    <template v-if="can_downvote == 1">
                                        <div class="col-3">
                                            <i
                                                v-if="song.vote <= 0"
                                                class="icon ti ti-arrow-big-up cursor-pointer"
                                                @click="vote(song.id, 1)"
                                            ></i>
                                            <i
                                                v-if="song.vote === 1"
                                                class="icon ti ti-arrow-big-up-filled cursor-pointer text-success"
                                                @click="vote(song.id, 0)"
                                            ></i>
                                        </div>
                                        <div class="col-6">
                                            {{ song.score }}
                                        </div>
                                        <div class="col-3">
                                            <i
                                                v-if="song.vote >= 0"
                                                class="icon ti ti-arrow-big-down cursor-pointer"
                                                @click="vote(song.id, -1)"
                                            ></i>
                                            <i
                                                v-if="song.vote === -1"
                                                class="icon ti ti-arrow-big-down-filled cursor-pointer text-danger"
                                                @click="vote(song.id, 0)"
                                            ></i>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <div class="col-4">
                                            <i
                                                v-if="song.vote <= 0"
                                                class="icon ti ti-heart cursor-pointer"
                                                @click="vote(song.id, 1)"
                                            ></i>
                                            <i
                                                v-if="song.vote === 1"
                                                class="icon ti ti-heart-filled cursor-pointer text-success"
                                                @click="vote(song.id, 0)"
                                            ></i>
                                        </div>
                                        <div class="col-8">
                                            {{ song.score }}
                                        </div>
                                    </template>
                                </div>
                            </div>
                            <div v-if="can_manage" class="col-auto lh-1">
                                <i class="icon ti ti-x cursor-pointer" @click="deleteSong(song.id)"></i>
                            </div>
                        </div>
                    </div>
                </TransitionGroup>
            </div>
        </div>
        <VueEternalLoading :load="getPage">
            <template #loading>
                <div class="text-center text-muted p-2">Loading upcoming songs...</div>
            </template>

            <template #no-results>
                <div class="text-center text-muted p-2">There are no upcoming songs</div>
            </template>

            <template #no-more>
                <div class="text-center text-muted p-2">There are no more upcoming songs</div>
            </template>

            <template #error>
                <div class="text-center text-muted p-2">Error fetching upcoming songs</div>
            </template>
        </VueEternalLoading>
    </div>
    <div v-if="message" class="position-fixed z-3 p-2 top-50 start-50 translate-middle fade show">
        <div class="alert alert-important alert-danger" role="alert">
            <div class="d-flex">
                <div>
                    <i class="icon ti ti-exclamation-circle"></i>
                </div>
                <div class="px-2">
                    {{ message }}
                </div>
            </div>
        </div>
    </div>
</template>
<style>
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

.song-length {
    padding-right: 1rem;
}

.whamageddon {
    padding-top: 20px;
}

.whamageddon::before {
    content: 'Whamageddon!';
    white-space: nowrap;
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
import {VueEternalLoading} from '@ts-pro/vue-eternal-loading';
import type {LoadAction} from '@ts-pro/vue-eternal-loading';
import type {LegacyArtist, LegacyUpcomingSong} from '../types';

export default defineComponent({
    components: {
        VueEternalLoading,
    },
    props: {
        can_manage: {type: [String, Number, Boolean]},
        can_downvote: {type: [String, Number, Boolean]},
        party: {type: String},
        timezone: {type: String, default: 'UTC'},
        initialstate: {type: String, required: true},
    },
    data() {
        return {
            page: 1,
            code: null as string | null,
            upcoming: [] as LegacyUpcomingSong[],
            message: '' as string,
        };
    },

    methods: {
        vote(song_id: number, value: number): void {
            window.axios
                .post(`/api/v1/parties/${this.party}/upcomingsongs/${song_id}/vote`, {
                    vote: value,
                })
                .then((response) => {
                    this.addOrUpdateItem(response.data.data);
                    this.sortUpcoming();
                })
                .catch((error) => {
                    if (error.response?.status !== 400) {
                        return;
                    }
                    this.message = error.response.data.message;
                    setTimeout(() => {
                        this.message = '';
                    }, 5000);
                });
        },

        deleteSong(song_id: number): void {
            this.removeItem(song_id);
            window.axios.delete(`/api/v1/parties/${this.party}/upcomingsongs/${song_id}`);
        },

        getPage({loaded}: LoadAction): void {
            window.axios
                .get(`/api/v1/parties/${this.party}/upcomingsongs`, {
                    params: {
                        page: this.page,
                    },
                })
                .then((response) => {
                    if (response.data.data.length > 0) {
                        this.page += 1;
                        response.data.data.forEach((newItem: LegacyUpcomingSong) => {
                            this.addOrUpdateItem(newItem);
                        });
                        this.sortUpcoming();
                    }
                    loaded(response.data.data.length, 20);
                })
                .catch(() => {});
        },

        combineArtists(artists: LegacyArtist[]): string {
            return artists.map((artist) => artist.name).join(', ');
        },

        sortUpcoming(): void {
            this.upcoming.sort((alpha, bravo) => {
                if (alpha.score === bravo.score) {
                    let diff = Math.sign(Date.parse(alpha.created_at) - Date.parse(bravo.created_at));
                    if (diff === 0) {
                        return Math.sign(alpha.id - bravo.id);
                    }
                    return diff;
                } else {
                    return Math.sign(bravo.score - alpha.score);
                }
            });
        },

        addOrUpdateItem(newItem: LegacyUpcomingSong, fromPubSub: boolean = false): void {
            let result = this.upcoming.some((existing, index) => {
                if (existing.id === newItem.id) {
                    if (fromPubSub) {
                        newItem.vote = existing.vote;
                    }
                    this.upcoming.splice(index, 1, newItem);
                    return true;
                }
                return false;
            });
            if (!result) {
                this.upcoming.push(newItem);
            }
        },

        removeItem(id: number): void {
            this.upcoming = this.upcoming.filter((song) => song.id !== id);
        },

        formatScheduled(iso: string): string {
            return new Intl.DateTimeFormat(undefined, {
                dateStyle: 'medium',
                timeStyle: 'short',
                timeZone: this.timezone,
            }).format(new Date(iso));
        },

        formatMs(ms: number): string {
            const mins = Math.floor(ms / 60000);
            const seconds = Math.floor((ms % 60000) / 1000).toFixed(0);
            return mins + ':' + (Number(seconds) < 10 ? '0' : '') + seconds;
        },
    },

    mounted() {
        window.Echo.private(`party.${this.party}`).listen(
            'UpcomingSong\\UpdatedEvent',
            (payload: LegacyUpcomingSong) => {
                this.addOrUpdateItem(payload, true);
                this.sortUpcoming();
            },
        );
        window.Echo.private(`party.${this.party}`).listen('UpcomingSong\\RemovedEvent', (payload: {id: number}) => {
            this.removeItem(payload.id);
        });
        const initial = JSON.parse(this.initialstate) as LegacyUpcomingSong[] | null;
        if (initial) {
            initial.forEach((item) => {
                this.addOrUpdateItem(item);
            });
        }
    },

    created() {},
});
</script>
