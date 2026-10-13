import {mount} from '@vue/test-utils';
import {describe, expect, it, vi} from 'vitest';
import Pagination from '@/components/Pagination.vue';
import TrackList from '@/components/TrackList.vue';
import TrackRow from '@/components/TrackRow.vue';
import type {Paginator, TrackAnnotation} from '@/types';

vi.mock('@inertiajs/vue3', () => ({
    Link: {props: ['href'], template: '<a :href="href"><slot /></a>'},
}));

const make = (overrides: Partial<TrackAnnotation> = {}): TrackAnnotation => ({
    provider_id: 'fake',
    id: 't1',
    name: 'Song',
    duration_ms: 185000,
    explicit: false,
    album: {id: 'a1', name: 'Album', image_url: 'https://img.test/a.jpg'},
    artists: [
        {id: 'r1', name: 'One'},
        {id: 'r2', name: 'Two'},
    ],
    queue: {in_queue: false, upcoming_id: null, score: 0, vote: null, requested_by: null},
    eligibility: {allowed: true, reason: null},
    ...overrides,
});

const inQueue = (vote: number | null) =>
    make({queue: {in_queue: true, upcoming_id: 4, score: 5, vote, requested_by: 'Jess'}});

describe('TrackRow', () => {
    it('shows details and emits request when allowed', async () => {
        const track = make();
        const wrapper = mount(TrackRow, {props: {track}});

        expect(wrapper.get('[data-test=track-name]').text()).toBe('Song');
        expect(wrapper.get('[data-test=track-artists]').text()).toBe('One, Two');
        expect(wrapper.text()).toContain('3:05');
        await wrapper.get('[data-test=request]').trigger('click');
        expect(wrapper.emitted('request')?.[0]).toEqual([track]);
    });

    it('disables request and shows the reason when blocked', async () => {
        const wrapper = mount(TrackRow, {props: {track: make({eligibility: {allowed: false, reason: 'Too long'}})}});

        expect(wrapper.get('[data-test=reason]').text()).toContain('Too long');
        expect(wrapper.get('[data-test=request]').attributes('disabled')).toBeDefined();
        await wrapper.get('[data-test=request]').trigger('click');
        expect(wrapper.emitted('request')).toBeUndefined();
    });

    it('labels the button and row when approval is needed', () => {
        const wrapper = mount(TrackRow, {props: {track: make(), needsApproval: true}});

        expect(wrapper.find('[data-test=needs-approval]').exists()).toBe(true);
        expect(wrapper.get('[data-test=request]').text()).toContain('Request approval');
    });

    it('shows awaiting approval and emits withdraw', async () => {
        const track = make();
        const wrapper = mount(TrackRow, {props: {track, awaitingApproval: true}});

        expect(wrapper.find('[data-test=awaiting-approval]').exists()).toBe(true);
        expect(wrapper.find('[data-test=request]').exists()).toBe(false);
        await wrapper.get('[data-test=withdraw]').trigger('click');
        expect(wrapper.emitted('withdraw')?.[0]).toEqual([track]);
    });

    it('shows score and heart vote when downvotes are off', async () => {
        const track = inQueue(null);
        const wrapper = mount(TrackRow, {props: {track}});

        expect(wrapper.get('[data-test=score]').text()).toBe('5');
        expect(wrapper.find('[data-test=request]').exists()).toBe(false);
        expect(wrapper.find('[data-test=downvote]').exists()).toBe(false);
        await wrapper.get('[data-test=heart]').trigger('click');
        expect(wrapper.emitted('vote')?.[0]).toEqual([track, 1]);
    });

    it('offers up and down votes and clears an existing vote', async () => {
        const track = inQueue(1);
        const wrapper = mount(TrackRow, {props: {track, canDownvote: true}});

        await wrapper.get('[data-test=upvote]').trigger('click');
        await wrapper.get('[data-test=downvote]').trigger('click');
        expect(wrapper.emitted('vote')).toEqual([
            [track, 0],
            [track, -1],
        ]);
    });

    it('shows the explicit badge', () => {
        expect(
            mount(TrackRow, {props: {track: make({explicit: true})}})
                .find('[data-test=explicit-badge]')
                .exists(),
        ).toBe(true);
        expect(
            mount(TrackRow, {props: {track: make()}})
                .find('[data-test=explicit-badge]')
                .exists(),
        ).toBe(false);
    });

    it('applies decoration', () => {
        const wrapper = mount(TrackRow, {
            props: {
                track: make(),
                decoration: {displayName: 'Renamed', label: 'Pick', class: 'is-pick', accent: 'red'},
            },
        });

        expect(wrapper.get('[data-test=track-name]').text()).toBe('Renamed');
        expect(wrapper.get('[data-test=decoration-label]').text()).toBe('Pick');
        expect(wrapper.classes()).toContain('is-pick');
        expect(wrapper.attributes('style')).toContain('red');
    });

    it('links album and artists only when routes are passed', () => {
        expect(mount(TrackRow, {props: {track: make()}}).findAll('a')).toHaveLength(0);

        const wrapper = mount(TrackRow, {
            props: {
                track: make(),
                albumRoute: (id: string) => `/albums/${id}`,
                artistRoute: (id: string) => ({url: `/artists/${id}`}),
            },
        });

        expect(wrapper.findAll('a').map((a) => a.attributes('href'))).toEqual([
            '/artists/r1',
            '/artists/r2',
            '/albums/a1',
        ]);
    });
});

const page = (data: TrackAnnotation[], lastPage = 2): Paginator<TrackAnnotation> => ({
    data,
    current_page: 1,
    last_page: lastPage,
    prev_page_url: null,
    next_page_url: '/p?page=2',
    links: [
        {url: null, label: '&laquo; Previous', active: false},
        {url: '/p?page=1', label: '1', active: true},
        {url: '/p?page=2', label: '2', active: false},
        {url: '/p?page=2', label: 'Next &raquo;', active: false},
    ],
});

describe('Pagination', () => {
    it('renders links from the paginator with the active page and a disabled previous', () => {
        const wrapper = mount(Pagination, {props: {paginator: page([])}});

        expect(wrapper.findAll('a').map((a) => a.attributes('href'))).toEqual(['/p?page=1', '/p?page=2', '/p?page=2']);
        expect(wrapper.get('.active').text()).toBe('1');
        expect(wrapper.findAll('.disabled')).toHaveLength(1);
        expect(wrapper.text()).toContain('Previous');
    });

    it('renders nothing for a single page', () => {
        expect(
            mount(Pagination, {props: {paginator: page([], 1)}})
                .find('nav')
                .exists(),
        ).toBe(false);
    });
});

describe('TrackList', () => {
    it('renders a row per track with pagination and forwards events', async () => {
        const first = make();
        const wrapper = mount(TrackList, {props: {tracks: page([first, make({id: 't2', name: 'Other'})])}});

        expect(wrapper.findAll('[data-test=track-row]')).toHaveLength(2);
        expect(wrapper.find('nav').exists()).toBe(true);
        await wrapper.findAll('[data-test=request]')[0].trigger('click');
        expect(wrapper.emitted('request')?.[0]).toEqual([first]);
    });

    it('shows the empty state when there are no tracks', () => {
        const wrapper = mount(TrackList, {props: {tracks: page([], 1), emptyTitle: 'Nothing here'}});

        expect(wrapper.get('[data-test=empty-state]').text()).toContain('Nothing here');
        expect(wrapper.find('[data-test=track-row]').exists()).toBe(false);
    });
});
