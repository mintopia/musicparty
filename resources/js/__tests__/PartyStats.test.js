import {mount} from '@vue/test-utils';
import {describe, expect, it, vi} from 'vitest';


vi.mock('@inertiajs/vue3', () => ({
    Head: {template: '<span />'},
    Link: {props: ['href'], template: '<a :href="href"><slot /></a>'},
}));

import Stats from '../pages/Party/Stats.vue';

const empty = {top_tracks: [], top_requesters: [], most_upvoted: [], most_downvoted: [], total_time_played_ms: 0};
const mountStats = (stats) => mount(Stats, {props: {party: {code: 'ABCD', name: 'Party'}, stats}});

describe('Party Stats', () => {
    it('shows empty states for an empty party', () => {
        const w = mountStats(empty);
        expect(w.find('[data-testid="stats-top-tracks-empty"]').exists()).toBe(true);
        expect(w.find('[data-testid="stats-most-downvoted-empty"]').exists()).toBe(true);
        expect(w.get('[data-testid="stats-time-played"]').text()).toBe('0m');
    });

    it('renders ranked rows and time played', () => {
        const w = mountStats({...empty, top_tracks: [{title: 'Song', artists: ['A'], plays: 2}], total_time_played_ms: 3900000});
        expect(w.findAll('[data-testid="stats-top-tracks-row"]')).toHaveLength(1);
        expect(w.get('[data-testid="stats-time-played"]').text()).toBe('1h 5m');
    });

    it('renders leaderboards and tolerates cached payloads without them', () => {
        const w = mountStats({...empty, upvote_leaderboard: [{nickname: 'Alice', votes: 3}]});
        expect(w.findAll('[data-testid="stats-upvote-leaderboard-row"]')).toHaveLength(1);
        expect(w.find('[data-testid="stats-downvote-leaderboard-empty"]').exists()).toBe(true);
    });
});
