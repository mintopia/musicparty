import {mount} from '@vue/test-utils';
import {beforeEach, describe, expect, it, vi} from 'vitest';

const del = vi.hoisted(() => vi.fn());
vi.mock('@inertiajs/vue3', () => ({router: {put: vi.fn(), delete: del}}));

import QueueList from '../Components/QueueList.vue';

const entry = (over = {}) => ({
    id: 1,
    status: 'queued',
    score: 0,
    my_vote: 0,
    track: {title: 'Song', artists: ['A'], artwork_url: null, duration_ms: 1000, provider_url: null},
    requested_by: {name: 'Me'},
    is_mine: true,
    has_other_votes: false,
    decorations: [],
    ...over,
});

const mountList = (queue, props = {}) => mount(QueueList, {props: {partyCode: 'ABCD', queue, ...props}});

describe('QueueList remove button', () => {
    beforeEach(() => del.mockClear());

    it('shows an enabled remove button for the requester and deletes the request', async () => {
        const button = mountList([entry()]).find('[data-testid=remove-request]');

        expect(button.exists()).toBe(true);
        expect(button.attributes('disabled')).toBeUndefined();
        await button.trigger('click');
        expect(del.mock.calls[0][0]).toBe('/parties/ABCD/requests/1');
    });

    it('disables the button with a reason once another member has voted', async () => {
        const button = mountList([entry({has_other_votes: true})]).find('[data-testid=remove-request]');

        expect(button.attributes('disabled')).toBeDefined();
        expect(button.attributes('title')).toBe('Someone has already voted on this request');
        await button.trigger('click');
        expect(del).not.toHaveBeenCalled();
    });

    it.each([
        ['another member\'s request', {is_mine: false}, {}],
        ['an Up Next request', {status: 'up_next'}, {}],
        ['a read-only list', {}, {readOnly: true}],
        ['an entry without ownership info', {is_mine: undefined}, {}],
    ])('hides the button for %s', (_, over, props) => {
        expect(mountList([entry(over)], props).find('[data-testid=remove-request]').exists()).toBe(false);
    });
});
