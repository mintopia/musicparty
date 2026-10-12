import {enableAutoUnmount, mount} from '@vue/test-utils';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';

const get = vi.hoisted(() => vi.fn());
const put = vi.hoisted(() => vi.fn());
vi.mock('@inertiajs/vue3', () => ({
    Head: {render: () => null},
    Link: {props: ['href'], template: '<a :href="href"><slot /></a>'},
    router: {get, put, delete: vi.fn(), reload: vi.fn(), post: vi.fn()},
    usePage: () => ({props: {errors: {}}}),
}));

import Show from '../pages/Party/Show.vue';
import QueueList from '../Components/QueueList.vue';
import TrackThumb from '../Components/TrackThumb.vue';

enableAutoUnmount(afterEach);

const item = (over = {}) => ({
    id: 1,
    status: 'queued',
    score: 2,
    my_vote: 0,
    track: {title: 'Song', artists: ['A'], artwork_url: null, duration_ms: 1000, provider_url: null},
    requested_by: {name: 'Me'},
    decorations: [],
    ...over,
});

const showProps = (partyOver = {}) => ({
    party: {code: 'FRI123', name: 'Friday', state: 'live', downvotes: true, selection_mode: 'deterministic', ...partyOver},
    membership: {role: 'member', banned: false},
    section: 'queue',
    nowPlaying: null,
    upNext: null,
    queue: [],
});

const mountList = (queue, props = {}) => mount(QueueList, {props: {partyCode: 'ABCD', queue, ...props}});

afterEach(() => {
    delete window.Echo;
});

beforeEach(() => {
    get.mockClear();
    const channel = () => {
        const c = {listen: () => c};
        return c;
    };
    window.Echo = {channel, private: channel, join: channel, leave: () => {}};
});

describe('Queue search box', () => {
    it('opens the Search section with the query', async () => {
        const w = mount(Show, {props: showProps()});
        await w.find('[data-testid=queue-search-input]').setValue(' queen ');
        await w.find('[data-testid=queue-search-form]').trigger('submit');
        expect(get).toHaveBeenCalledWith('/parties/FRI123/search', {q: 'queen'});
    });

    it('does nothing for an empty query', async () => {
        const w = mount(Show, {props: showProps()});
        await w.find('[data-testid=queue-search-form]').trigger('submit');
        expect(get).not.toHaveBeenCalled();
    });
});

describe('Queue heading by selection mode', () => {
    it('reads Queue without a hint in deterministic mode', () => {
        const w = mount(Show, {props: showProps()});
        expect(w.find('[data-testid=queue-heading]').text()).toBe('Queue');
        expect(w.find('[data-testid=queue-weighted-hint]').exists()).toBe(false);
    });

    it('reads Upcoming Songs with a hint in weighted mode', () => {
        const w = mount(Show, {props: showProps({selection_mode: 'weighted'})});
        expect(w.find('[data-testid=queue-heading]').text()).toBe('Upcoming Songs');
        expect(w.find('[data-testid=queue-weighted-hint]').text()).toContain('more likely');
    });
});

describe('Vote icons', () => {
    it('outlines both arrows when the member has not voted', () => {
        const w = mountList([item()]);
        expect(w.find('[data-testid=vote-up] svg').attributes('fill')).toBe('none');
        expect(w.find('[data-testid=vote-down] svg').attributes('fill')).toBe('none');
    });

    it('fills the up arrow for an upvote only', () => {
        const w = mountList([item({my_vote: 1})]);
        expect(w.find('[data-testid=vote-up] svg').attributes('fill')).toBe('currentColor');
        expect(w.find('[data-testid=vote-down] svg').attributes('fill')).toBe('none');
    });

    it('fills the down arrow for a downvote only', () => {
        const w = mountList([item({my_vote: -1})]);
        expect(w.find('[data-testid=vote-down] svg').attributes('fill')).toBe('currentColor');
        expect(w.find('[data-testid=vote-up] svg').attributes('fill')).toBe('none');
    });

    it('shows a heart and no down button when downvotes are disabled', () => {
        const w = mountList([item()], {downvotesEnabled: false});
        expect(w.find('[data-testid=vote-down]').exists()).toBe(false);
        expect(w.find('[data-testid=vote-up] svg path').attributes('d')).toContain('a5 5 0');
    });

    it('fills the heart when the member has voted', () => {
        const w = mountList([item({my_vote: 1})], {downvotesEnabled: false});
        expect(w.find('[data-testid=vote-up] svg').attributes('fill')).toBe('currentColor');
    });
});

describe('Vote errors and row sizing', () => {
    it('centres a refused vote message', async () => {
        put.mockImplementation((url, data, opts) => {
            opts.onStart();
            opts.onError({vote: 'Try again in 10 minutes'});
            opts.onFinish();
        });
        const w = mountList([item()]);
        await w.find('[data-testid=vote-down]').trigger('click');
        const err = w.find('[data-testid=vote-error]');
        expect(err.text()).toBe('Try again in 10 minutes');
        expect(err.classes()).toContain('text-center');
    });

    it('uses the v2 row padding and cover size', () => {
        const w = mountList([item()]);
        expect(w.find('[data-testid=queue-item]').classes()).toContain('py-5');
        expect(mount(TrackThumb).find('[data-testid=cover-placeholder]').classes()).toContain('h-[60px]');
    });
});
