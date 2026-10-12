<script setup>
import {usePartyPresence} from '../../composables/usePartyPresence';
import {Head, Link} from '@inertiajs/vue3';
import Icon from '../../Components/Icon.vue';
import TrackThumb from '../../Components/TrackThumb.vue';
import {absoluteTime, formatDuration, relativeTime} from '../../lib/format';

const props = defineProps({
    party: {type: Object, required: true},
    song: {type: Object, required: true},
});

usePartyPresence(props.party.code);

const appearanceUrl = (id) => `/parties/${props.party.code}/songs/${id}`;

const cardClass = 'rounded border border-border bg-surface';
const tableClass = 'w-full text-left text-sm';
const headClass = 'px-4 py-2 font-medium text-muted';
const cellClass = 'px-4 py-2';
const titleClass = 'text-xs font-bold uppercase tracking-wide text-muted';
const buttonClass = 'flex min-h-11 items-center gap-2 rounded border px-3 text-sm';
</script>

<template>
    <Head :title="`${song.title} - ${party.name}`" />
    <div class="flex flex-col gap-4 px-4 pb-4 pt-6">
        <header class="flex flex-wrap items-center justify-between gap-2">
            <h1 class="text-xl font-semibold" data-testid="song-title">{{ song.title }}</h1>
            <Link :href="`/parties/${party.code}/history`" class="flex min-h-11 items-center rounded border border-border px-3 text-sm hover:text-primary">Back to history</Link>
        </header>

        <section :class="cardClass" data-testid="song-card">
            <div class="flex gap-4 px-5 py-5">
                <TrackThumb :src="song.artwork_url" :title="song.album" class="!h-32 !w-32 hidden lg:block" />
                <dl class="grid flex-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <dt :class="titleClass">Artists</dt>
                        <dd data-testid="song-artists">
                            {{ song.artists.join(', ') }}
                        </dd>
                    </div>
                    <div>
                        <dt :class="titleClass">Album</dt>
                        <dd data-testid="song-album">
                            {{ song.album }}
                        </dd>
                    </div>
                    <div>
                        <dt :class="titleClass">Duration</dt>
                        <dd data-testid="song-duration">{{ formatDuration(song.duration_ms) }}</dd>
                    </div>
                    <div>
                        <dt :class="titleClass">Requested</dt>
                        <dd><span data-testid="song-requested" :title="absoluteTime(song.requested_at)">{{ relativeTime(song.requested_at) }}</span></dd>
                    </div>
                    <div>
                        <dt :class="titleClass">Requested By</dt>
                        <dd data-testid="song-requester">{{ song.requester ? song.requester.name : 'Fallback Track' }}</dd>
                    </div>
                    <div>
                        <dt :class="titleClass">Sent to Spotify</dt>
                        <dd>
                            <span v-if="song.sent_at" data-testid="song-sent" :title="absoluteTime(song.sent_at)">{{ relativeTime(song.sent_at) }}</span>
                            <span v-else data-testid="song-not-sent" class="text-muted">Not Sent</span>
                        </dd>
                    </div>
                </dl>
            </div>
            <footer class="flex flex-wrap justify-end gap-2 border-t border-border px-5 py-3">
                <Link
                    v-if="song.can_moderate && song.moderate_url"
                    :href="song.moderate_url"
                    data-testid="song-moderate"
                    :class="[buttonClass, 'border-danger text-danger hover:bg-danger/10']"
                >Moderate</Link>
                <a
                    v-if="song.spotify_url"
                    :href="song.spotify_url"
                    target="_blank"
                    rel="noopener"
                    data-testid="song-spotify"
                    :class="[buttonClass, 'border-transparent bg-green-600 text-white hover:opacity-90']"
                >View on Spotify</a>
            </footer>
        </section>

        <div class="grid gap-4 md:grid-cols-2">
            <section data-testid="song-votes">
                <h2 class="mb-2 text-lg font-semibold">Votes</h2>
                <div v-if="song.votes.length" :class="[cardClass, 'overflow-x-auto']">
                    <table :class="tableClass">
                        <thead>
                            <tr class="border-b border-border">
                                <th scope="col" :class="headClass">User</th>
                                <th scope="col" :class="[headClass, 'text-center']">Vote</th>
                                <th scope="col" :class="headClass">Time</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="(vote, index) in song.votes" :key="index" data-testid="vote-row">
                                <td :class="cellClass">{{ vote.member.name }}</td>
                                <td :class="[cellClass, 'text-center']" :data-direction="vote.direction">
                                    <Icon :name="vote.direction === 'up' ? 'arrowUp' : 'arrowDown'" :class="['inline h-4 w-4', vote.direction === 'up' ? 'text-accent' : 'text-danger']" />
                                    <span class="sr-only">{{ vote.direction === 'up' ? 'Upvote' : 'Downvote' }}</span>
                                </td>
                                <td :class="cellClass"><span :title="absoluteTime(vote.voted_at)">{{ relativeTime(vote.voted_at) }}</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else data-testid="votes-empty" class="text-sm text-muted">There have been no votes</p>
            </section>

            <section data-testid="song-appearances">
                <h2 class="mb-2 text-lg font-semibold">Other Appearances</h2>
                <div v-if="song.appearances.length" :class="[cardClass, 'overflow-x-auto']">
                    <table :class="tableClass">
                        <thead>
                            <tr class="border-b border-border">
                                <th scope="col" :class="headClass">Requested</th>
                                <th scope="col" :class="headClass">Sent to Spotify</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="appearance in song.appearances" :key="appearance.id" data-testid="appearance-row">
                                <td :class="cellClass">
                                    <Link :href="appearanceUrl(appearance.id)" :title="absoluteTime(appearance.requested_at)" class="text-primary hover:underline">{{ relativeTime(appearance.requested_at) }}</Link>
                                </td>
                                <td :class="cellClass">
                                    <span v-if="appearance.sent_at" :title="absoluteTime(appearance.sent_at)">{{ relativeTime(appearance.sent_at) }}</span>
                                    <span v-else class="text-muted">Not Sent to Spotify</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else data-testid="appearances-empty" class="text-sm text-muted">This song has no other appearances</p>
            </section>
        </div>

        <section data-testid="song-ratings" class="md:w-1/2 md:pr-2">
            <h2 class="mb-2 text-lg font-semibold">Ratings</h2>
            <div v-if="song.ratings.length" :class="[cardClass, 'overflow-x-auto']">
                <table :class="tableClass">
                    <thead>
                        <tr class="border-b border-border">
                            <th scope="col" :class="headClass">User</th>
                            <th scope="col" :class="[headClass, 'text-center']">Rating</th>
                            <th scope="col" :class="headClass">Time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="(rating, index) in song.ratings" :key="index" data-testid="rating-row">
                            <td :class="cellClass">{{ rating.member.name }}</td>
                            <td :class="[cellClass, 'text-center']" :data-direction="rating.direction">
                                <Icon :name="rating.direction === 'up' ? 'thumbUp' : 'thumbDown'" :class="['inline h-4 w-4', rating.direction === 'up' ? 'text-accent' : 'text-danger']" />
                                <span class="sr-only">{{ rating.direction === 'up' ? 'Liked' : 'Disliked' }}</span>
                            </td>
                            <td :class="cellClass"><span :title="absoluteTime(rating.rated_at)">{{ relativeTime(rating.rated_at) }}</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else data-testid="ratings-empty" class="text-sm text-muted">There have been no ratings</p>
        </section>
    </div>
</template>
