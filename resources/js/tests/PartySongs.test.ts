import {mount} from '@vue/test-utils';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import SearchBar from '@/components/SearchBar.vue';
import Index from '@/pages/Parties/Songs/Index.vue';
import ShowSong from '@/pages/Parties/Songs/Show.vue';
import type {LegacyPartyState} from '@/types';

const inertia = vi.hoisted(() => ({get: vi.fn(), reload: vi.fn(), put: vi.fn(), delete: vi.fn()}));

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
        providerName: 'Spotify',
        filters: {order: 'created_at', order_direction: 'desc' as const, q: 'artist:abba'},
        counts: {total: 42, sent: 31, waiting: 11, scheduled: 2, flagged: 3, mine: 5},
        songs: paginator([row(1), row(2, when)]),
    };

    beforeEach(() => inertia.get.mockReset());

    it('renders the rows with relative times and unsent state', () => {
        const wrapper = mount(Index, {props});
        const rows = wrapper.findAll('[data-test="song-row"]');

        expect(rows).toHaveLength(2);
        expect(rows[0].text()).toContain('A, B');
        expect(rows[0].text()).toContain('Not yet');
        expect(rows[0].text()).not.toContain('Not Sent');
        expect(rows[1].text()).toContain('3 hours ago');
        expect(rows[0].get('a').attributes('href')).toBe('/parties/ABCD/songs/1');
        expect(wrapper.text()).toContain('Showing 1 to 2 of 2 Requests');
        expect(wrapper.text()).toContain('Requested by');
        expect(wrapper.text()).toContain('Sent to Player');
    });

    it('shows the requester, badges and a row menu', async () => {
        const wrapper = mount(Index, {
            props: {
                ...props,
                songs: paginator([
                    {
                        ...row(1),
                        scheduledLabel: '22:30',
                        flagged: true,
                        requestedBy: {nickname: 'Jess', isYou: true},
                        removeUrl: '/parties/ABCD/songs/1',
                    },
                ]),
            },
        });
        const first = wrapper.get('[data-test="song-row"]');

        expect(first.get('[data-test="requested-by"]').text()).toContain('Jess');
        expect(first.get('[data-test="requested-by"]').text()).toContain('You');
        expect(first.get('[data-test="scheduled-badge"]').text()).toContain('Scheduled 22:30');
        expect(first.find('[data-test="flagged-badge"]').exists()).toBe(true);
        expect(first.find('[data-test="row-menu-items"]').exists()).toBe(false);

        await first.get('[data-test="row-menu-toggle"]').trigger('click');
        expect(first.get('[data-test="row-menu-items"]').text()).toContain('Open Request');
        expect(first.get('[data-test="row-menu-items"]').text()).toContain('Schedule Request');

        await first.get('[data-test="remove-request"]').trigger('click');
        expect(inertia.delete).toHaveBeenCalledWith('/parties/ABCD/songs/1');
    });

    it('uses the mockup placeholder, hint and chip counts', () => {
        const wrapper = mount(Index, {props});

        expect(wrapper.get('[data-test="search-input"]').attributes('placeholder')).toBe(
            'Search tracks, artists, people… or try sent:no',
        );
        expect(wrapper.get('[data-test="search-hint"]').text()).toBe(
            'Words match every column. Add filters like sent:no, artist:abba or by:ravi; the chips set the same filters.',
        );
        expect(wrapper.get('[data-test="search-count"]').text()).toBe('2 of 42 Requests');
        expect(wrapper.findAll('[data-test="search-chip"]').map((chip) => chip.text())).toEqual([
            'Sent to Player 31',
            'Waiting 11',
            'Scheduled 2',
            'Flagged 3',
            'Mine 5',
            'Artist: abba',
        ]);
    });

    it('sends the typed query as q', () => {
        const wrapper = mount(Index, {props});

        wrapper.getComponent(SearchBar).vm.$emit('update:modelValue', 'sent:no artist:abba hit');

        expect(inertia.get).toHaveBeenCalledWith('/parties/ABCD/songs', {
            q: 'sent:no artist:abba hit',
            order: 'created_at',
            order_direction: 'desc',
        });
    });

    it('toggles chips into the same tokens, replacing the opposite sent state', async () => {
        const wrapper = mount(Index, {props: {...props, filters: {...props.filters, q: 'sent:yes artist:abba'}}});
        const chips = wrapper.findAll('[data-test="search-chip"]');

        expect(chips[0].attributes('aria-pressed')).toBe('true');
        expect(chips[1].attributes('aria-pressed')).toBe('false');

        await chips[1].trigger('click');
        expect(inertia.get).toHaveBeenLastCalledWith('/parties/ABCD/songs', {
            q: 'artist:abba sent:no',
            order: 'created_at',
            order_direction: 'desc',
        });

        await chips[4].trigger('click');
        expect(inertia.get).toHaveBeenLastCalledWith('/parties/ABCD/songs', {
            q: 'artist:abba sent:no by:me',
            order: 'created_at',
            order_direction: 'desc',
        });

        await chips[5].trigger('click');
        expect(inertia.get).toHaveBeenLastCalledWith('/parties/ABCD/songs', {
            q: 'sent:no by:me',
            order: 'created_at',
            order_direction: 'desc',
        });
    });

    it('repeats the query in the empty state with a clear button', () => {
        const wrapper = mount(Index, {
            props: {...props, filters: {...props.filters, q: 'zzz'}, songs: {...paginator([]), from: null, to: null}},
        });

        expect(wrapper.get('[data-test="empty-state"] .empty-title code').text()).toBe('zzz');
        expect(wrapper.get('.empty-title').text()).toBe('No Requests match zzz');
        expect(wrapper.findAll('a').filter((link) => link.text() === 'Clear filters')).toHaveLength(1);
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
        removeUrl: '/parties/ABCD/songs/1',
    };
    const base = {
        party,
        playerState: idle,
        providerName: 'Spotify',
        trackUrl: null,
        song,
        votes: paginator([]),
        aiReview: null,
        history: [{icon: 'ti-music-plus', text: 'Fallback requested it', at: when, url: null}],
    };

    it('shows fallback requester, empty states and no external link', () => {
        const wrapper = mount(ShowSong, {props: base});

        expect(wrapper.text()).toContain('Fallback Track');
        expect(wrapper.text()).toContain('Not yet');
        expect(wrapper.text()).toContain('No votes yet');
        expect(wrapper.text()).toContain('This Request was not reviewed by AI.');
        expect(wrapper.text()).not.toContain('View on Spotify');
    });

    it('shows votes, the AI review outcome, history and the track link', () => {
        const wrapper = mount(ShowSong, {
            props: {
                ...base,
                trackUrl: 'https://open.spotify.com/track/t',
                song: {...song, requestedBy: {nickname: 'Sam', url: '/parties/ABCD/users/1'}},
                votes: paginator([{nickname: 'Kim', userUrl: '/u/2', value: 1, createdAt: when}]),
                aiReview: {outcome: 'allow', outcomeLabel: 'Allowed', reason: 'Fits.', criterion: null, url: '/parties/ABCD/ai-reviews/9'},
                history: [
                    {icon: 'ti-music-plus', text: 'Sam requested it', at: when, url: null},
                    {icon: 'ti-history', text: 'Requested again', at: when, url: '/parties/ABCD/songs/5'},
                ],
            },
        });

        expect(wrapper.text()).toContain('View on Spotify');
        expect(wrapper.get('[data-test="votes"]').find('.ti-arrow-big-up-filled').exists()).toBe(true);
        expect(wrapper.get('[data-test="vote-summary"]').text()).toBe('2 votes, score +1');
        expect(wrapper.get('[data-test="ai-outcome"]').text()).toBe('Allowed');
        expect(wrapper.get('[data-test="ai-reason"]').text()).toContain('No criterion applied.');
        expect(wrapper.get('[data-test="history"]').text()).toContain('Sam requested it');
        const links = wrapper.findAll('a').map((link) => link.attributes('href'));
        expect(links).toContain('/parties/ABCD/users/1');
        expect(links).toContain('/parties/ABCD/ai-reviews/9');
        expect(links).toContain('/parties/ABCD/songs/5');
    });

    it('removes the request from the page menu', async () => {
        const wrapper = mount(ShowSong, {props: base});

        await wrapper.get('[data-test="row-menu-toggle"]').trigger('click');
        await wrapper.get('[data-test="remove-request"]').trigger('click');

        expect(inertia.delete).toHaveBeenCalledWith('/parties/ABCD/songs/1');
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
