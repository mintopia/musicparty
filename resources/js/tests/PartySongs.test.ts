import {mount} from '@vue/test-utils';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import Index from '@/pages/Parties/Songs/Index.vue';
import ShowSong from '@/pages/Parties/Songs/Show.vue';
import type {LegacyPartyState} from '@/types';

const inertia = vi.hoisted(() => ({get: vi.fn(), reload: vi.fn(), put: vi.fn()}));

vi.mock('@inertiajs/vue3', async () => {
    const {reactive} = await import('vue');
    return {
        Head: {render: () => null},
        Link: {props: ['href'], template: '<a :href="href"><slot /></a>'},
        router: inertia,
        useForm: (data: Record<string, unknown>) =>
            reactive({...data, errors: {}, processing: false, put: inertia.put}),
    };
});
vi.mock('@/layouts/AppLayout.vue', () => ({
    default: {template: '<div><slot name="breadcrumbs" /><slot name="header" /><slot /></div>'},
}));
vi.mock('@/routes', () => ({home: () => ({url: '/'})}));
vi.mock('@/components/PartyPlayer.vue', () => ({default: {template: '<div data-test="party-player" />'}}));

const idle: LegacyPartyState = {now: null, next: null, current: null, status: null};
const party = {code: 'ABCD', name: 'Party', canManage: false};
const when = {iso: '2030-01-01 10:00:00', human: '3 hours ago'};

const row = (id: number, queuedAt: typeof when | null = null) => ({
    id,
    name: `Song ${id}`,
    artists: ['A', 'B'],
    albumName: 'Album',
    imageUrl: '/a.png',
    votes: 2,
    score: 1,
    createdAt: when,
    queuedAt,
    url: `/parties/ABCD/songs/${id}`,
});

const paginator = <T>(data: T[]) => ({
    data,
    current_page: 1,
    last_page: 1,
    prev_page_url: null,
    next_page_url: null,
    links: [],
    from: 1,
    to: data.length,
    total: data.length,
});

describe('Party songs index', () => {
    const props = {
        party,
        playerState: idle,
        moderationTabs: {flaggedCount: 0, aiReviewEnabled: false},
        providerName: 'Spotify',
        filters: {order: 'created_at', order_direction: 'desc' as const, artist: 'abba'},
        types: [{code: 'queued', name: 'Queued'}],
        songs: paginator([row(1), row(2, when)]),
    };

    beforeEach(() => inertia.get.mockReset());

    it('renders the rows with relative times and unsent state', () => {
        const wrapper = mount(Index, {props});
        const rows = wrapper.findAll('[data-test="song-row"]');

        expect(rows).toHaveLength(2);
        expect(rows[0].text()).toContain('A, B');
        expect(rows[0].text()).toContain('Not Sent to Spotify');
        expect(rows[1].text()).toContain('3 hours ago');
        expect(rows[0].get('a').attributes('href')).toBe('/parties/ABCD/songs/1');
        expect(wrapper.text()).toContain('Showing 1 to 2 of 2 entries');
        expect(wrapper.text()).toContain('Sent to Spotify');
    });

    it('submits only the filled filters, keeping the sort order', async () => {
        const wrapper = mount(Index, {props});

        await wrapper.get('#name').setValue('hit');
        await wrapper.get('form').trigger('submit');

        expect(inertia.get).toHaveBeenCalledWith('/parties/ABCD/songs', {
            name: 'hit',
            artist: 'abba',
            order: 'created_at',
            order_direction: 'desc',
        });
    });
});

describe('Party songs show', () => {
    const song = {
        id: 1,
        name: 'Hit',
        imageUrl: '/a.png',
        albumName: 'Album',
        albumUrl: '/parties/ABCD/songs?album=Album',
        artists: [
            {name: 'A', url: '/parties/ABCD/songs?artist=A'},
            {name: 'B', url: '/parties/ABCD/songs?artist=B'},
        ],
        duration: '3:00',
        votes: 2,
        score: 1,
        createdAt: when,
        queuedAt: null,
        scheduledFor: null,
        scheduledForInput: '',
        requestedBy: null,
        moderateUrl: '/parties/ABCD/moderation/create?track=t',
    };
    const base = {
        party,
        playerState: idle,
        moderationTabs: {flaggedCount: 0, aiReviewEnabled: false},
        providerName: 'Spotify',
        trackUrl: null,
        song,
        votes: paginator([]),
        other: [],
        ratings: paginator([]),
    };

    it('shows fallback requester, empty states and no external link', () => {
        const wrapper = mount(ShowSong, {props: base});

        expect(wrapper.text()).toContain('Fallback Track');
        expect(wrapper.text()).toContain('Not Sent');
        expect(wrapper.text()).toContain('There have been no votes');
        expect(wrapper.text()).toContain('This song has no other appearances');
        expect(wrapper.text()).toContain('There have been no ratings');
        expect(wrapper.text()).not.toContain('View on Spotify');
        expect(wrapper.text()).toContain('Moderate');
    });

    it('lists votes, appearances and ratings with the track link', () => {
        const wrapper = mount(ShowSong, {
            props: {
                ...base,
                trackUrl: 'https://open.spotify.com/track/t',
                song: {...song, requestedBy: {nickname: 'Sam', url: '/parties/ABCD/users/1'}},
                votes: paginator([{nickname: 'Kim', userUrl: '/u/2', value: 1, createdAt: when}]),
                other: [{id: 5, url: '/parties/ABCD/songs/5', votes: 1, score: 1, createdAt: when, queuedAt: null}],
                ratings: paginator([{nickname: 'Lee', userUrl: '/u/3', value: -1, createdAt: when}]),
            },
        });

        expect(wrapper.text()).toContain('View on Spotify');
        expect(wrapper.get('[data-test="votes"]').find('.ti-arrow-big-up-filled').exists()).toBe(true);
        expect(wrapper.get('[data-test="ratings"]').find('.ti-thumb-down-filled').exists()).toBe(true);
        expect(wrapper.get('[data-test="other"]').text()).toContain('3 hours ago');
        expect(wrapper.findAll('a').map((link) => link.attributes('href'))).toContain('/parties/ABCD/users/1');
    });

    it('hides the schedule editor from non-moderators', () => {
        const wrapper = mount(ShowSong, {props: base});

        expect(wrapper.find('[data-test="schedule-card"]').exists()).toBe(false);
    });

    it('shows the current schedule and saves a new one', async () => {
        const wrapper = mount(ShowSong, {
            props: {
                ...base,
                party: {...party, canManage: true},
                song: {...song, scheduledFor: when, scheduledForInput: '2030-01-01T20:00'},
            },
        });

        expect(wrapper.get('[data-test="schedule-current"]').text()).toContain('3 hours ago');
        const input = wrapper.get('input[type="datetime-local"]');
        expect((input.element as HTMLInputElement).value).toBe('2030-01-01T20:00');

        await input.setValue('2030-01-02T21:30');
        await wrapper.get('[data-test="schedule-card"]').trigger('submit');

        expect(inertia.put).toHaveBeenCalledWith('/parties/ABCD/songs/1', expect.anything());
        expect(wrapper.get('[data-test="schedule-card"]').text()).not.toContain('Not scheduled');
    });

    it('clears the schedule by submitting an empty value', async () => {
        const wrapper = mount(ShowSong, {
            props: {
                ...base,
                party: {...party, canManage: true},
                song: {...song, scheduledFor: when, scheduledForInput: '2030-01-01T20:00'},
            },
        });

        await wrapper.get('[data-test="schedule-clear"]').trigger('click');

        expect((wrapper.get('input[type="datetime-local"]').element as HTMLInputElement).value).toBe('');
        expect(inertia.put).toHaveBeenCalledTimes(1);
    });

    it('says when nothing is scheduled', () => {
        const wrapper = mount(ShowSong, {props: {...base, party: {...party, canManage: true}}});

        expect(wrapper.get('[data-test="schedule-current"]').text()).toContain('Not scheduled');
    });
});
