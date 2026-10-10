import {enableAutoUnmount, mount} from '@vue/test-utils';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    Head: {render: () => null},
    Link: {props: ['href'], template: '<a :href="href"><slot /></a>'},
}));

import Log from '../pages/Party/Log.vue';

enableAutoUnmount(afterEach);

let listeners;
let echo;

beforeEach(() => {
    listeners = {};
    const channel = {listen: vi.fn((event, cb) => { listeners[event] = cb; return channel; })};
    echo = {private: vi.fn(() => channel), leave: vi.fn()};
    window.Echo = echo;
});

afterEach(() => {
    delete window.Echo;
});

const props = () => ({
    party: {code: 'FRI1', name: 'Friday'},
    entries: {data: [{id: 1, action: 'party.created', subject: null, details: null, actor: 'Jo', actor_kind: 'user', created_at: '2026-01-01T00:00:00Z'}], links: {}},
});

describe('Party log realtime', () => {
    it('prepends entries from the moderator channel without reload', async () => {
        const w = mount(Log, {props: props()});
        expect(echo.private).toHaveBeenCalledWith('party.FRI1.moderators');

        listeners['.party_log.entry_added']({id: 2, action: 'member.banned', subject: 'Sam'});
        await w.vm.$nextTick();
        const rows = w.findAll('[data-testid=log-entry]');
        expect(rows).toHaveLength(2);
        expect(rows[0].text()).toContain('Banned Sam');

        listeners['.party_log.entry_added']({id: 2, action: 'member.banned', subject: 'Sam'});
        await w.vm.$nextTick();
        expect(w.findAll('[data-testid=log-entry]')).toHaveLength(2);
    });

    it('leaves the channel on unmount', () => {
        mount(Log, {props: props()}).unmount();
        expect(echo.leave).toHaveBeenCalledWith('party.FRI1.moderators');
    });
});
