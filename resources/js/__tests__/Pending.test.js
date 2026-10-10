import {enableAutoUnmount, mount} from '@vue/test-utils';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    Head: {render: () => null},
    Link: {props: ['href'], template: '<a :href="href"><slot /></a>'},
    router: {post: vi.fn(), delete: vi.fn(), reload: vi.fn()},
    usePage: () => ({props: {flash: {}}}),
}));

import Pending from '../pages/Party/Pending.vue';

enableAutoUnmount(afterEach);

let listeners;
let echo;

beforeEach(() => {
    listeners = {};
    const channel = {listen: vi.fn((event, cb) => { listeners[event] = cb; return channel; })};
    echo = {private: vi.fn(() => channel), join: vi.fn(() => channel), leave: vi.fn()};
    window.Echo = echo;
});

afterEach(() => {
    delete window.Echo;
});

const props = () => ({
    party: {code: 'FRI1', name: 'Friday'},
    canModerate: true,
    requests: [{id: 1, track: {title: 'Old', artists: ['A']}, requested_by: {name: 'Alex'}}],
});

describe('Pending requests realtime', () => {
    it('adds and removes items from moderator channel events without reload', async () => {
        const w = mount(Pending, {props: props()});
        expect(echo.private).toHaveBeenCalledWith('party.FRI1.moderators');

        listeners['.pending_request.added']({request_id: 2, title: 'New', artists: ['B', 'C'], requested_by_member_id: 9});
        await w.vm.$nextTick();
        expect(w.get('[data-testid=pending-2]').text()).toContain('New');
        expect(w.get('[data-testid=pending-2]').text()).toContain('B, C');

        listeners['.pending_request.added']({request_id: 2, title: 'New', artists: [], requested_by_member_id: 9});
        await w.vm.$nextTick();
        expect(w.findAll('[data-testid^=pending-]').filter((n) => n.attributes('data-testid') === 'pending-2')).toHaveLength(1);

        listeners['.pending_request.resolved']({request_id: 1, status: 'approved'});
        listeners['.pending_request.resolved']({request_id: 2, status: 'rejected'});
        await w.vm.$nextTick();
        expect(w.find('[data-testid=pending-1]').exists()).toBe(false);
        expect(w.get('[data-testid=pending-empty]').exists()).toBe(true);
    });

    it('leaves the channel on unmount', () => {
        mount(Pending, {props: props()}).unmount();
        expect(echo.leave).toHaveBeenCalledWith('party.FRI1.moderators');
    });
});
