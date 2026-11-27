import {flushPromises, mount} from '@vue/test-utils';
import {defineComponent, h} from 'vue';
import axios from 'axios';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import Search from '@/pages/Parties/Search.vue';
import type {
    AlbumResult,
    ArtistResult,
    LegacyPartyState,
    SearchOverviewResults,
    SearchPage,
    SearchTypeResults,
    TrackAnnotation,
} from '@/types';

const inertia = vi.hoisted(() => ({get: vi.fn(), reload: vi.fn()}));
const deferred = vi.hoisted(() => ({mode: 'loaded' as 'loaded' | 'loading' | 'rescued'}));

vi.mock('@inertiajs/vue3', () => ({
    Head: {render: () => null},
    Deferred: defineComponent({
        props: {data: {type: String, required: true}},
        setup:
            (_props, {slots}) =>
            () =>
                deferred.mode === 'loading'
                    ? h('div', slots.fallback?.())
                    : deferred.mode === 'rescued'
                      ? h('div', slots.rescue?.())
                      : h('div', slots.default?.({reloading: false})),
    }),
    Link: {props: ['href'], template: '<a :href="href"><slot /></a>'},
    router: inertia,
    usePage: () => ({props: {flash: {}, auth: {user: null}}}),
}));

vi.mock('@/layouts/AppLayout.vue', () => ({
    default: {template: '<div><slot name="breadcrumbs" /><slot name="header" /><slot /></div>'},
}));

vi.mock('@/composables/useEcho', () => ({
    useEcho: () => ({
        onReconnect: () => undefined,
        trackConnection: () => ({value: true}),
        privateChannel: () => ({listen: () => undefined}),
        publicChannel: () => ({listen: () => undefined}),
    }),
}));

vi.mock('@/routes', () => ({home: () => ({url: '/'})}));

function track(id: string, queue: Partial<TrackAnnotation['queue']> = {}, allowed = true): TrackAnnotation {
    return {
        provider_id: 'spotify',
        id,
        name: `Track ${id}`,
        duration_ms: 180000,
        explicit: false,
        external_url: null,
        provider_name: 'Spotify',
        album: {id: 'a', name: 'Album', image_url: null},
        artists: [{id: 'x', name: 'Artist'}],
        queue: {in_queue: false, upcoming_id: null, score: 0, vote: null, requested_by: null, ...queue},
        eligibility: {
            allowed,
            outcome: allowed ? 'allow' : 'reject',
            reason: allowed ? null : 'Played in the last hour',
        },
    };
}

const album = (id: string, overrides: Partial<AlbumResult> = {}): AlbumResult => ({
    id,
    name: `Album ${id}`,
    image_url: `https://img.test/${id}.jpg`,
    album_type: 'Album',
    artists: [{id: 'x', name: 'Semisonic'}],
    release_year: '1998',
    ...overrides,
});

const artist = (id: string, overrides: Partial<ArtistResult> = {}): ArtistResult => ({
    id,
    name: `Artist ${id}`,
    image_url: `https://img.test/${id}.jpg`,
    ...overrides,
});

function overview(overrides: Partial<SearchOverviewResults> = {}): SearchOverviewResults {
    return {
        view: 'overview',
        top: {kind: 'track', track: track('top')},
        tracks: [track('top'), track('t1'), track('t2'), track('t3')],
        albums: [album('al1'), album('al2')],
        artists: [artist('ar1'), artist('ar2')],
        empty: false,
        weak: false,
        ...overrides,
    };
}

function page<T>(data: T[], current = 1, last = 100): SearchPage<T> {
    const prev = current > 1 ? `/parties/ABCD/search?query=q&type=track&page=${current - 1}` : null;
    const next = current < last ? `/parties/ABCD/search?query=q&type=track&page=${current + 1}` : null;

    return {
        data,
        current_page: current,
        last_page: last,
        prev_page_url: prev,
        next_page_url: next,
        links: [
            {url: prev, label: '&laquo; Previous', active: false},
            {url: `/parties/ABCD/search?query=q&page=${current}`, label: String(current), active: true},
            {url: next, label: 'Next &raquo;', active: false},
        ],
        total: 1000,
        from: (current - 1) * 10 + 1,
        to: (current - 1) * 10 + data.length,
    };
}

const idle: LegacyPartyState = {now: null, next: null, current: null, status: null};
const party = {code: 'ABCD', name: 'Party', downvotes: false, canManage: false};
const originalAdapter = axios.defaults.adapter;

const mountSearch = (
    results: unknown,
    params: {query: string | null; perPage?: number; type?: string | null} = {query: 'q'},
) =>
    mount(Search, {
        props: {party, params: {perPage: 10, type: null, ...params}, results, playerState: idle} as never,
    });

describe('Party search overview', () => {
    beforeEach(() => {
        inertia.get.mockReset();
        inertia.reload.mockReset();
        deferred.mode = 'loaded';
    });

    afterEach(() => {
        axios.defaults.adapter = originalAdapter;
    });

    it('shows the first track as the Top result beside the tracks, with a working Add', async () => {
        axios.defaults.adapter = () =>
            Promise.resolve({data: {}, status: 201, statusText: 'Created', headers: {}, config: {}} as never);
        const wrapper = mountSearch(overview());

        expect(wrapper.get('[data-test="top-name"]').text()).toBe('Track top');
        expect(wrapper.get('[data-test="top-artists"]').text()).toContain('Song');
        expect(wrapper.findAll('[data-test="section-head"] h2').map((h2) => h2.text())).toEqual([
            'Top result',
            'Tracks',
            'Albums',
            'Artists',
        ]);
        expect(wrapper.findAll('[data-test="tracks-section"] [data-test="track-row"]')).toHaveLength(4);

        await wrapper.get('[data-test="top-request"]').trigger('click');
        await flushPromises();

        expect(inertia.reload).toHaveBeenCalledWith({only: ['results']});
    });

    it('shows Upvote and who requested a Top result track that is already queued', async () => {
        axios.defaults.adapter = () =>
            Promise.resolve({
                data: {data: {score: 5, vote: 1, user: 'Lunchbox'}},
                status: 200,
                statusText: 'OK',
                headers: {},
                config: {},
            } as never);
        const queued = track('top', {in_queue: true, upcoming_id: 9, score: 4, requested_by: 'Lunchbox'});
        const wrapper = mountSearch(overview({top: {kind: 'track', track: queued}}));

        expect(wrapper.get('[data-test="top-note"]').text()).toContain('Requested by Lunchbox · 4 votes');
        expect(wrapper.find('[data-test="top-request"]').exists()).toBe(false);

        await wrapper.get('[data-test="top-upvote"]').trigger('click');
        await flushPromises();

        expect(wrapper.get('[data-test="top-note"]').text()).toContain('5 votes');
    });

    it('disables Add on the Top result with the eligibility reason', () => {
        const wrapper = mountSearch(overview({top: {kind: 'track', track: track('top', {}, false)}}));

        expect(wrapper.get('[data-test="top-request"]').attributes('disabled')).toBeDefined();
        expect(wrapper.get('[data-test="top-reason"]').text()).toContain('Played in the last hour');
    });

    it('shows an artist as the Top result with See artist and no Add', () => {
        const wrapper = mountSearch(overview({top: {kind: 'artist', artist: artist('ar1', {name: 'Semisonic'})}}), {
            query: 'Semisonic',
        });

        expect(wrapper.get('[data-test="top-name"]').text()).toBe('Semisonic');
        expect(wrapper.get('[data-test="top-see-artist"]').attributes('href')).toBe(
            '/parties/ABCD/artists/ar1?query=Semisonic',
        );
        expect(wrapper.find('[data-test="top-request"]').exists()).toBe(false);
        expect(wrapper.findAll('[data-test="tracks-section"] [data-test="track-row"]')).toHaveLength(4);
    });

    it('links See all to the single-type view for each section and the tiles to their pages', () => {
        const wrapper = mountSearch(overview(), {query: 'Closing Time'});

        expect(wrapper.findAll('[data-test="see-all"]').map((a) => [a.text(), a.attributes('href')])).toEqual([
            ['See all tracks', '/parties/ABCD/search?query=Closing%20Time&type=track'],
            ['See all albums', '/parties/ABCD/search?query=Closing%20Time&type=album'],
            ['See all artists', '/parties/ABCD/search?query=Closing%20Time&type=artist'],
        ]);
        expect(wrapper.get('[data-test="album-tile"]').attributes('href')).toBe(
            '/parties/ABCD/albums/al1?query=Closing%20Time',
        );
        expect(wrapper.get('[data-test="album-byline"]').text()).toBe('Semisonic · 1998');
        expect(wrapper.get('[data-test="artist-tile"]').attributes('href')).toBe(
            '/parties/ABCD/artists/ar1?query=Closing%20Time',
        );
    });

    it('omits a section whose type came back empty, heading included', () => {
        const wrapper = mountSearch(overview({artists: []}));

        expect(wrapper.find('[data-test="artists-section"]').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('Artists');
        expect(wrapper.text()).not.toContain('No artists');
        expect(wrapper.find('[data-test="albums-section"]').exists()).toBe(true);
    });

    it('leads with the albums when there is no Top result and no tracks', () => {
        const wrapper = mountSearch(overview({top: null, tracks: [], artists: []}));

        expect(wrapper.find('[data-test="top-result"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="tracks-section"]').exists()).toBe(false);
        expect(wrapper.findAll('[data-test="album-tile"]')).toHaveLength(2);
    });

    it('lays albums and artists out in a fluid tile row and exposes full names as titles', () => {
        const wrapper = mountSearch(overview());

        expect(wrapper.get('[data-test="album-row"]').classes()).toContain('search-tile-row');
        expect(wrapper.get('[data-test="artist-row"]').classes()).toContain('search-tile-row-round');
        const albumName = wrapper.get('[data-test="album-name"]');
        expect(albumName.classes()).toContain('clamp-2');
        expect(albumName.attributes('title')).toBe(albumName.text());
        expect(wrapper.get('[data-test="album-byline"]').classes()).toContain('text-truncate');
        expect(wrapper.get('[data-test="artist-name"]').attributes('title')).toBe(
            wrapper.get('[data-test="artist-name"]').text(),
        );
    });

    it('falls back to an icon when an album or artist has no image', () => {
        const wrapper = mountSearch(
            overview({albums: [album('al1', {image_url: null})], artists: [artist('ar1', {image_url: null})]}),
        );

        expect(wrapper.findAll('[data-test="cover-fallback"]').length).toBeGreaterThanOrEqual(2);
    });

    it('shows a single No results card when every type is empty', () => {
        const wrapper = mountSearch(overview({top: null, tracks: [], albums: [], artists: [], empty: true}), {
            query: 'Clozing Tyme',
        });

        expect(wrapper.get('[data-test="results-empty"]').text()).toContain('No results for “Clozing Tyme”');
        expect(wrapper.text()).toContain("Check the spelling or try the artist's name.");
        expect(wrapper.find('[data-test="section-head"]').exists()).toBe(false);
    });
});

describe('Party search suggestions and weak matches', () => {
    beforeEach(() => {
        deferred.mode = 'loaded';
    });

    const suggestions = {
        nowPlaying: {name: 'Midnight City', artist: 'M83', image_url: null, query: 'M83'},
        recent: ['Levels', 'daft punk'],
        artists: [{id: 'ar1', name: 'Daft Punk', image_url: null, requests: 5}],
    };

    it('offers the playing track, recent searches and top artists on the empty page', () => {
        const wrapper = mount(Search, {
            props: {
                party,
                params: {query: null, perPage: 10, type: null},
                results: null,
                suggestions,
                playerState: idle,
            } as never,
        });

        expect(wrapper.get('[data-test="more-like"]').attributes('href')).toContain('?query=M83');
        expect(wrapper.get('[data-test="more-like"]').text()).toContain('More like Midnight City');
        expect(wrapper.findAll('[data-test="recent-search"]').map((a) => a.attributes('href'))).toEqual([
            expect.stringContaining('query=Levels'),
            expect.stringContaining('query=daft%20punk'),
        ]);
        expect(wrapper.get('[data-test="artist-subtitle"]').text()).toBe('5 Requests');
    });

    it('words a track you queued as requested by you with its age', () => {
        const wrapper = mountSearch(
            overview({
                tracks: [
                    track('t1', {
                        in_queue: true,
                        requested_by: 'Mina',
                        requested_by_you: true,
                        requested_ago: 'just now',
                    }),
                ],
            }),
        );

        expect(wrapper.get('[data-test="track-row"]').text()).toContain('Requested by you · just now');
    });

    it('shows no suggestions once a search is made', () => {
        const wrapper = mountSearch(overview());

        expect(wrapper.find('[data-test="search-suggestions"]').exists()).toBe(false);
    });

    it('says there are no close matches above weak results only', () => {
        const weak = mountSearch(overview({weak: true}), {query: 'lucky dafft punk'});
        const strong = mountSearch(overview());

        expect(weak.get('[data-test="no-close-matches"]').text()).toContain('No close matches for “lucky dafft punk”');
        expect(weak.get('[data-test="no-close-matches"]').text()).toContain('These are the nearest results we found.');
        expect(weak.get('[data-test="no-close-matches"]').text()).not.toContain('Spotify');
        expect(strong.find('[data-test="no-close-matches"]').exists()).toBe(false);
    });

    it('lists weak results as plain full-width tracks without a Top result card', () => {
        const weak = mountSearch(overview({weak: true}), {query: 'lucky dafft punk'});

        expect(weak.find('[data-test="top-result"]').exists()).toBe(false);
        expect(weak.get('[data-test="tracks-section"]').classes()).toContain('col-12');
        expect(weak.findAll('[data-test="tracks-section"] [data-test="request"]')).toHaveLength(4);
    });
});

describe('Party search states', () => {
    beforeEach(() => {
        inertia.get.mockReset();
        inertia.reload.mockReset();
        deferred.mode = 'loaded';
    });

    it('draws the whole overview as a skeleton with every heading while loading', () => {
        deferred.mode = 'loading';
        const wrapper = mountSearch(undefined);

        const loading = wrapper.get('[data-test="results-loading"]');
        expect(loading.attributes('aria-busy')).toBe('true');
        expect(loading.classes()).toContain('placeholder-wave');
        expect(wrapper.find('[data-test="top-placeholder"]').exists()).toBe(true);
        expect(wrapper.findAll('[data-test="track-placeholder"]')).toHaveLength(4);
        expect(wrapper.find('[data-test="album-placeholders"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="artist-placeholders"]').exists()).toBe(true);
        expect(wrapper.findAll('[data-test="section-head"] h2').map((h2) => h2.text())).toEqual([
            'Top result',
            'Tracks',
            'Albums',
            'Artists',
        ]);
        expect(wrapper.find('[data-test="track-row"]').exists()).toBe(false);
    });

    it('draws a plain list skeleton while a single-type view loads', () => {
        deferred.mode = 'loading';
        const wrapper = mountSearch(undefined, {query: 'q', type: 'album'});

        expect(wrapper.find('[data-test="top-placeholder"]').exists()).toBe(false);
        expect(wrapper.findAll('[data-test="track-placeholder"]').length).toBeGreaterThan(0);
        expect(wrapper.get('[data-test="results-loading"]').attributes('aria-busy')).toBe('true');
    });

    it('shows the error card with Retry that reloads only the results and keeps the query', async () => {
        const wrapper = mountSearch({
            error: {code: 'timeout', message: "Spotify didn't answer in time.", retryable: true},
        });

        const error = wrapper.get('[data-test="results-error"]');
        expect(error.text()).toContain("Search didn't load");
        expect(error.text()).toContain('Your search is still in the box.');
        expect((wrapper.get('#query').element as HTMLInputElement).value).toBe('q');

        await wrapper.get('[data-test="retry"]').trigger('click');

        expect(inertia.reload).toHaveBeenCalledWith(expect.objectContaining({only: ['results']}));
    });
});

describe('Party search single-type view', () => {
    beforeEach(() => {
        inertia.get.mockReset();
        inertia.reload.mockReset();
        deferred.mode = 'loaded';
    });

    afterEach(() => {
        Reflect.deleteProperty(window, 'matchMedia');
    });

    const trackType = (current = 1, items = [track('a1'), track('a2')]): SearchTypeResults => ({
        view: 'type',
        type: 'track',
        total: 1000,
        capped: true,
        items: page(items, current),
    });

    it('shows a back link, the heading with a 1,000+ count and the range with pagination', () => {
        const wrapper = mountSearch(trackType(), {query: 'Closing Time', type: 'track'});

        expect(wrapper.get('[data-test="back-to-overview"]').text()).toContain('All results for "Closing Time"');
        expect(wrapper.get('[data-test="back-to-overview"]').attributes('href')).toBe(
            '/parties/ABCD/search?query=Closing%20Time',
        );
        expect(wrapper.get('[data-test="type-title"]').text()).toBe('Tracks');
        expect(wrapper.get('[data-test="type-count"]').text()).toBe('1,000+');
        expect(wrapper.get('[data-test="range"]').text()).toBe('1–2 of 1,000+');
        expect(wrapper.findAll('[data-test="track-row"]')).toHaveLength(2);
        expect(wrapper.find('[data-test="type-pagination"] .pagination').exists()).toBe(true);
        expect(wrapper.find('[data-test="top-result"]').exists()).toBe(false);
    });

    it('shows an exact count when the total is not capped', () => {
        const wrapper = mountSearch(
            {
                view: 'type',
                type: 'track',
                total: 57,
                capped: false,
                items: {...page([track('a1')], 1, 6), total: 57, to: 1},
            },
            {query: 'q', type: 'track'},
        );

        expect(wrapper.get('[data-test="type-count"]').text()).toBe('57');
    });

    it('lists albums as rows with kind, artist and year linking to the album page', () => {
        const wrapper = mountSearch(
            {
                view: 'type',
                type: 'album',
                total: 412,
                capped: false,
                items: page([album('al1', {album_type: 'Single', release_year: '2003'})], 1, 42),
            },
            {query: 'q', type: 'album'},
        );

        const row = wrapper.get('[data-test="entity-row"]');
        expect(wrapper.get('[data-test="type-title"]').text()).toBe('Albums');
        expect(row.attributes('href')).toBe('/parties/ABCD/albums/al1?query=q');
        expect(wrapper.get('[data-test="entity-subtitle"]').text()).toBe('Single · Semisonic · 2003');
    });

    it('lists artists as round rows, with an icon when there is no image', () => {
        const wrapper = mountSearch(
            {
                view: 'type',
                type: 'artist',
                total: 37,
                capped: false,
                items: page([artist('ar1'), artist('ar2', {image_url: null})], 1, 4),
            },
            {query: 'q', type: 'artist'},
        );

        expect(wrapper.get('[data-test="type-title"]').text()).toBe('Artists');
        expect(wrapper.findAll('[data-test="entity-row"]')).toHaveLength(2);
        expect(wrapper.findAll('[data-test="entity-row"]')[0].attributes('href')).toBe(
            '/parties/ABCD/artists/ar1?query=q',
        );
        expect(wrapper.findAll('[data-test="cover-fallback"]')).toHaveLength(1);
        expect(wrapper.get('[data-test="entity-subtitle"]').text()).toBe('Artist');
    });

    it('shows No results when the chosen type has nothing', () => {
        const wrapper = mountSearch(
            {
                view: 'type',
                type: 'artist',
                total: 0,
                capped: false,
                items: {...page([], 1, 1), total: 0, from: null, to: null},
            },
            {query: 'zzz', type: 'artist'},
        );

        expect(wrapper.get('[data-test="results-empty"]').text()).toContain('No results for “zzz”');
    });

    it('loads more by fetching the next page in place and appending its rows', async () => {
        const wrapper = mountSearch(trackType(), {query: 'q', type: 'track'});

        expect(wrapper.get('[data-test="showing"]').text()).toBe('Showing 2 of 1,000+');
        expect(wrapper.get('[data-test="load-more"]').text()).toBe('Load more tracks');

        await wrapper.get('[data-test="load-more"]').trigger('click');

        expect(inertia.get).toHaveBeenCalledWith(
            '/parties/ABCD/search?query=q&type=track&page=2',
            {},
            expect.objectContaining({only: ['results', 'params'], preserveState: true, preserveScroll: true}),
        );

        await wrapper.setProps({results: trackType(2, [track('b1'), track('b2'), track('b3')])});

        expect(wrapper.findAll('[data-test="track-row"]')).toHaveLength(5);
        expect(wrapper.get('[data-test="showing"]').text()).toBe('Showing 5 of 1,000+');
    });

    it('replaces the rows instead of appending when the page changes some other way', async () => {
        const wrapper = mountSearch(trackType(), {query: 'q', type: 'track'});

        await wrapper.setProps({results: trackType(2, [track('b1')])});

        expect(wrapper.findAll('[data-test="track-row"]')).toHaveLength(1);
    });

    it('keeps the loaded pages when the current page is only refreshed', async () => {
        const wrapper = mountSearch(trackType(), {query: 'q', type: 'track'});
        await wrapper.get('[data-test="load-more"]').trigger('click');
        await wrapper.setProps({results: trackType(2, [track('b1')])});

        await wrapper.setProps({
            results: trackType(2, [track('b1', {in_queue: true, upcoming_id: 3, requested_by: 'Sam'})]),
        });

        expect(wrapper.findAll('[data-test="track-row"]')).toHaveLength(3);
    });

    it('hides Load more on the last page', () => {
        const wrapper = mountSearch(
            {view: 'type', type: 'track', total: 12, capped: false, items: {...page([track('a1')], 2, 2), total: 12}},
            {query: 'q', type: 'track'},
        );

        expect(wrapper.find('[data-test="load-more"]').exists()).toBe(false);
    });

    it('uses the compact phone rows on a narrow screen', async () => {
        const listeners: (() => void)[] = [];
        Object.defineProperty(window, 'matchMedia', {
            configurable: true,
            value: () => ({
                matches: true,
                addEventListener: (_event: string, cb: () => void) => listeners.push(cb),
                removeEventListener: () => undefined,
            }),
        });
        const wrapper = mountSearch(trackType(), {query: 'q', type: 'track'});
        await flushPromises();

        expect(wrapper.find('[data-test="track-album-inline"]').exists()).toBe(true);
    });

    it('keeps the full rows on a wide screen', () => {
        const wrapper = mountSearch(trackType(), {query: 'q', type: 'track'});

        expect(wrapper.find('[data-test="track-album-inline"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="track-album"]').classes()).not.toContain('d-none');
    });
});
