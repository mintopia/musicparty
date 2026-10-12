import {mount} from '@vue/test-utils';
import {describe, expect, it, vi} from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    Head: {template: '<span />'},
    Link: {props: ['href'], template: '<a :href="href"><slot /></a>'},
    router: {get: vi.fn()},
    usePage: () => ({url: '/parties/ABCD/songs/5', props: {errors: {}}}),
}));

import Song from '../Song.vue';

const member = (name) => ({id: 1, name});
const song = (overrides = {}) => ({
    kind: 'request',
    id: 5,
    play_id: null,
    title: 'Rewind the Night',
    artists: ['The Lowlands', 'Kit'],
    album: 'Night Album',
    artwork_url: null,
    duration_ms: 200000,
    explicit: false,
    provider_track_id: 'abc',
    isrc: null,
    status: 'played',
    requested_at: '2026-10-09T11:00:00+00:00',
    sent_at: null,
    spotify_url: 'https://open.spotify.com/track/abc',
    requester: {id: 2, name: 'Alex'},
    votes: [],
    ratings: [],
    appearances: [],
    can_moderate: false,
    moderate_url: null,
    ...overrides,
});
const mountSong = (overrides = {}) => mount(Song, {props: {party: {code: 'ABCD', name: 'Party'}, song: song(overrides)}});

describe('Party Song', () => {
    const moderateUrl = '/parties/ABCD/blocklist?type=track_id&value=abc&name=Rewind%20the%20Night';

    it('renders the moderate link when allowed', () => {
        const w = mountSong({can_moderate: true, moderate_url: moderateUrl});
        expect(w.get('[data-testid="song-moderate"]').attributes('href')).toBe(moderateUrl);
    });

    it('hides the moderate link when not allowed or url missing', () => {
        expect(mountSong({moderate_url: moderateUrl}).find('[data-testid="song-moderate"]').exists()).toBe(false);
        expect(mountSong({can_moderate: true}).find('[data-testid="song-moderate"]').exists()).toBe(false);
    });

    it('shows details, fallback requester and not sent', () => {
        const w = mountSong({requester: null});
        expect(w.get('[data-testid="song-duration"]').text()).toBe('3:20');
        expect(w.get('[data-testid="song-requester"]').text()).toBe('Fallback Track');
        expect(w.get('[data-testid="song-not-sent"]').text()).toBe('Not Sent');
        const spotify = w.get('[data-testid="song-spotify"]');
        expect(spotify.attributes('target')).toBe('_blank');
    });

    it('renders a fallback play with its played time as sent', () => {
        const w = mountSong({kind: 'play', requester: null, status: 'played', play_id: 5, sent_at: '2026-10-09T11:00:00+00:00'});
        expect(w.get('[data-testid="song-requester"]').text()).toBe('Fallback Track');
        expect(w.find('[data-testid="song-sent"]').exists()).toBe(true);
        expect(w.get('[data-testid="song-vote-count"]').text()).toBe('0');
    });

    it('renders empty states', () => {
        const w = mountSong();
        expect(w.get('[data-testid="votes-empty"]').text()).toBe('There have been no votes');
        expect(w.get('[data-testid="ratings-empty"]').text()).toBe('There have been no ratings');
        expect(w.find('[data-testid="appearances-empty"]').exists()).toBe(true);
    });

    it('renders votes, ratings and appearances rows', () => {
        const w = mountSong({
            sent_at: '2026-10-09T12:00:00+00:00',
            votes: [{member: member('Sam'), direction: 'up', voted_at: '2026-10-09T11:05:00+00:00'}, {member: member('Bo'), direction: 'down', voted_at: '2026-10-09T11:06:00+00:00'}],
            ratings: [{member: member('Sam'), direction: 'down', rated_at: '2026-10-09T12:30:00+00:00'}],
            appearances: [
                {kind: 'request', id: 9, url: '/parties/ABCD/songs/9', requested_at: '2026-10-01T10:00:00+00:00', sent_at: null},
                {kind: 'play', id: 12, url: '/parties/ABCD/plays/12', requested_at: '2026-10-02T10:00:00+00:00', sent_at: '2026-10-02T10:00:00+00:00'},
            ],
        });
        const votes = w.findAll('[data-testid="vote-row"]');
        expect(votes).toHaveLength(2);
        expect(votes[0].attributes('data-direction') ?? votes[0].find('td[data-direction]').attributes('data-direction')).toBe('up');
        expect(votes[1].find('td[data-direction]').attributes('data-direction')).toBe('down');
        expect(w.findAll('[data-testid="rating-row"]')).toHaveLength(1);
        const rows = w.findAll('[data-testid="appearance-row"]');
        expect(rows[0].get('a').attributes('href')).toBe('/parties/ABCD/songs/9');
        expect(rows[0].text()).toContain('Not Sent to Spotify');
        expect(rows[1].get('a').attributes('href')).toBe('/parties/ABCD/plays/12');
        expect(w.get('[data-testid="song-vote-count"]').text()).toBe('2');
        expect(w.get('[data-testid="song-score"]').text()).toBe('0');
        expect(w.find('[data-testid="votes-empty"]').exists()).toBe(false);
        expect(w.find('[data-testid="song-not-sent"]').exists()).toBe(false);
    });
});
