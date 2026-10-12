import {mount} from '@vue/test-utils';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {nextTick} from 'vue';

vi.mock('@inertiajs/vue3', () => ({router: {put: vi.fn(), delete: vi.fn()}}));

import QueueList from '../Components/QueueList.vue';

const entry = (id) => ({
    id,
    status: 'queued',
    score: 0,
    my_vote: 0,
    track: {title: `Song ${id}`, artists: ['A'], artwork_url: null, duration_ms: 1000, provider_url: null},
    requested_by: {name: 'Me'},
    decorations: [],
});

const build = (n, from = 1) => Array.from({length: n}, (_, i) => entry(from + i));

let callbacks;

beforeEach(() => {
    callbacks = [];
    vi.stubGlobal('IntersectionObserver', class {
        constructor(cb) {
            callbacks.push(cb);
        }
        observe() {}
        disconnect() {}
    });
});

afterEach(() => vi.unstubAllGlobals());

const reachEnd = async () => {
    callbacks.at(-1)([{isIntersecting: true}]);
    await nextTick();
};

const count = (w) => w.findAll('[data-testid=queue-item]').length;

describe('QueueList windowing', () => {
    it('shows 20 of 75, loads to 40 at the sentinel, and keeps the window when the queue reorders', async () => {
        const queue = build(75);
        const wrapper = mount(QueueList, {props: {partyCode: 'ABCD', queue}});

        expect(count(wrapper)).toBe(20);
        await reachEnd();
        expect(count(wrapper)).toBe(40);

        await wrapper.setProps({queue: [...queue].reverse()});
        expect(count(wrapper)).toBe(40);
        expect(wrapper.find('[data-testid=queue-item]').text()).toContain('Song 75');
    });

    it('drops the sentinel once everything is shown', async () => {
        const wrapper = mount(QueueList, {props: {partyCode: 'ABCD', queue: build(25)}});

        expect(wrapper.find('[data-testid=queue-sentinel]').exists()).toBe(true);
        await reachEnd();
        expect(count(wrapper)).toBe(25);
        expect(wrapper.find('[data-testid=queue-sentinel]').exists()).toBe(false);
    });

    it('does not shrink the window when the queue gets shorter', async () => {
        const wrapper = mount(QueueList, {props: {partyCode: 'ABCD', queue: build(75)}});
        await reachEnd();
        await wrapper.setProps({queue: build(30)});

        expect(count(wrapper)).toBe(30);
    });
});
