import {enableAutoUnmount, mount} from '@vue/test-utils';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';

const get = vi.fn();
const put = vi.fn();
const del = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    router: {get: (...args) => get(...args), put: (...args) => put(...args), delete: (...args) => del(...args)},
    useForm: (data) => ({...data, post: vi.fn()}),
}));

import SearchPanel from '../Components/SearchPanel.vue';

const hit = (over = {}) => ({
    provider_track_id: 'abc',
    title: 'Dance Floor Gravity',
    artists: ['Nova Kids'],
    album: 'Orbit',
    artwork_url: null,
    duration_ms: 241000,
    queued: false,
    provider_url: 'https://open.spotify.com/track/abc',
    popularity: 50,
    ...over,
});

const render = (over = {}) => mount(SearchPanel, {
    props: {party: {code: 'FRI123', downvotes: true}, results: [hit()], searchQuery: 'dance', searchPage: 1, searchHasMore: false, ...over},
});

enableAutoUnmount(afterEach);

beforeEach(() => {
    get.mockReset();
    put.mockReset();
    del.mockReset();
});

describe('SearchPanel', () => {
    it.each([[0, 0], [50, 3], [100, 5], [null, 0]])('renders stars for popularity %s', (popularity, stars) => {
        const wrapper = render({results: [hit({popularity})]});
        const el = wrapper.get('[data-testid="popularity-stars"]');
        expect(el.attributes('aria-label')).toBe(`${stars} of 5 stars`);
        expect(el.findAll('svg')).toHaveLength(5);
        expect(el.findAll('svg[fill="currentColor"]')).toHaveLength(stars);
    });

    it('links each result to Spotify', () => {
        const link = render().get('[data-testid="view-on-spotify"]');
        expect(link.text()).toBe('View on Spotify');
        expect(link.attributes('href')).toBe('https://open.spotify.com/track/abc');
    });

    it('hides next when there are no more results', () => {
        expect(render().find('[data-testid="search-next"]').exists()).toBe(false);
    });

    it('requests the next page', async () => {
        const wrapper = render({searchHasMore: true, searchPage: 1});
        await wrapper.get('[data-testid="search-next"]').trigger('click');
        expect(get).toHaveBeenCalledTimes(1);
        const [url, params, options] = get.mock.calls[0];
        expect(url).toBe('/parties/FRI123/search');
        expect(params).toEqual({q: 'dance', page: 2});
        expect(options.only).toEqual(['results', 'search_query', 'search_error', 'search_page', 'search_has_more']);
    });

    it('resets to page one on a new search', async () => {
        const wrapper = render({searchHasMore: true, searchPage: 3});
        await wrapper.get('[data-testid="search-input"]').setValue('other');
        await wrapper.get('form').trigger('submit');
        expect(get.mock.calls[0][1]).toEqual({q: 'other'});
    });

    it('disables the request button and shows the reason for an ineligible result', () => {
        const wrapper = render({results: [hit({eligible: false, reason: 'Tracks can be at most 3 minutes long.'})]});
        expect(wrapper.get('[data-testid="request-button"]').attributes('disabled')).toBeDefined();
        expect(wrapper.get('[data-testid="ineligible-reason"]').text()).toBe('Tracks can be at most 3 minutes long.');
    });

    it('shows vote controls instead of a request button for a queued result', () => {
        const wrapper = render({results: [hit({queued: true, queued_request_id: 9, my_vote: 1, score: 2})]});
        expect(wrapper.find('[data-testid="request-button"]').exists()).toBe(false);
        expect(wrapper.get('[data-testid="vote-up"]').attributes('aria-pressed')).toBe('true');
        expect(wrapper.get('[data-testid="result-score"]').text()).toBe('2');
    });

    it('votes on the queued request from a result', async () => {
        const wrapper = render({results: [hit({queued: true, queued_request_id: 9, my_vote: 0, score: 0})]});
        await wrapper.get('[data-testid="vote-up"]').trigger('click');
        expect(put).toHaveBeenCalledWith('/parties/FRI123/requests/9/vote', {value: 'up'}, expect.any(Object));
        await wrapper.get('[data-testid="vote-down"]').trigger('click');
        expect(put).toHaveBeenLastCalledWith('/parties/FRI123/requests/9/vote', {value: 'down'}, expect.any(Object));
    });

    it('retracts a vote by repeating it', async () => {
        const wrapper = render({results: [hit({queued: true, queued_request_id: 9, my_vote: -1, score: -1})]});
        await wrapper.get('[data-testid="vote-down"]').trigger('click');
        expect(del).toHaveBeenCalledWith('/parties/FRI123/requests/9/vote', expect.any(Object));
        expect(put).not.toHaveBeenCalled();
    });

    it('matches the v2 empty state', () => {
        const wrapper = render({results: [], searchQuery: 'zzzz'});
        expect(wrapper.get('[data-testid="search-empty"]').text()).toBe('No results found');
        expect(wrapper.html()).toMatchSnapshot();
    });

    it('styles the search input like v2', () => {
        const wrapper = render();
        expect(wrapper.get('[data-testid="search-input"]').attributes('placeholder')).toBe('Search…');
        expect(wrapper.find('[data-testid="search-icon"]').exists()).toBe(true);
    });
});
