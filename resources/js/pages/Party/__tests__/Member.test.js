import {mount} from '@vue/test-utils';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';

const router = vi.hoisted(() => ({get: vi.fn(), put: vi.fn(), delete: vi.fn()}));

vi.mock('@inertiajs/vue3', () => ({
    Head: {template: '<span />'},
    Link: {props: ['href'], template: '<a :href="href"><slot /></a>'},
    router,
    usePage: () => ({url: '/parties/ABCD/members/7', props: {errors: {}}}),
}));

import Member from '../Member.vue';

const member = (overrides = {}) => ({id: 7, nickname: 'Alex', avatar: null, role: 'guest', banned: false, isYou: false, joinedAt: '2026-10-01T10:00:00Z', ...overrides});
const vote = (id, title, value = 1) => ({id, title, artists: ['Artist'], value, votedAt: '2026-10-02T10:00:00Z', requestId: id});
const mountMember = (props = {}) => mount(Member, {
    props: {
        party: {code: 'ABCD', name: 'Party'},
        member: member(),
        requests: [{id: 1, title: 'Asked', artists: ['A'], status: 'queued', requestedAt: '2026-10-02T10:00:00Z'}],
        votes: {data: [vote(1, 'Blue Monday'), vote(2, 'Other', -1)], total: 2, from: 1, to: 2, last_page: 1, links: []},
        filters: {search: ''},
        abilities: {canChangeRoles: true, canBan: true},
        ...props,
    },
});

describe('Party Member detail', () => {
    beforeEach(() => vi.clearAllMocks());
    afterEach(() => vi.useRealTimers());

    it('lists requests and votes', () => {
        const w = mountMember();
        expect(w.findAll('[data-testid="member-request-row"]')).toHaveLength(1);
        const votes = w.findAll('[data-testid="member-vote-row"]');
        expect(votes).toHaveLength(2);
        expect(votes[0].text()).toContain('Blue Monday');
        expect(w.findAll('[data-testid="member-vote-value"]').map((v) => v.text())).toEqual(['Up', 'Down']);
    });

    it('shows empty states', () => {
        const w = mountMember({requests: [], votes: {data: [], total: 0, last_page: 1, links: []}});
        expect(w.find('[data-testid="member-requests-empty"]').exists()).toBe(true);
        expect(w.find('[data-testid="member-votes-empty"]').exists()).toBe(true);
    });

    it('searches votes after a debounce', async () => {
        vi.useFakeTimers();
        const w = mountMember();
        await w.get('[data-testid="member-votes-search"]').setValue('blue');
        vi.advanceTimersByTime(300);
        expect(router.get).toHaveBeenCalledWith('/parties/ABCD/members/7', {search: 'blue'}, expect.any(Object));
    });

    it('bans and unbans', async () => {
        const w = mountMember();
        await w.get('[data-testid="member-ban"]').trigger('click');
        expect(router.put).toHaveBeenCalledWith('/parties/ABCD/members/7/ban', {}, expect.any(Object));
        const banned = mountMember({member: member({banned: true})});
        expect(banned.find('[data-testid="member-banned"]').exists()).toBe(true);
        await banned.get('[data-testid="member-unban"]').trigger('click');
        expect(router.delete).toHaveBeenCalledWith('/parties/ABCD/members/7/ban', expect.any(Object));
    });

    it('hides actions without abilities', () => {
        const w = mountMember({abilities: {canChangeRoles: false, canBan: false}});
        expect(w.find('[data-testid="member-ban"]').exists()).toBe(false);
        expect(w.find('[data-testid="select-field"]').exists()).toBe(false);
        expect(w.get('[data-testid="member-role"]').text()).toBe('Guest');
    });

    it('changes the role', async () => {
        const w = mountMember();
        const select = w.get('[data-testid="select-field"]');
        await select.get('button').trigger('click');
        await select.findAll('[role=option]').find((o) => o.text() === 'VIP').trigger('mousedown');
        expect(router.put).toHaveBeenCalledWith('/parties/ABCD/members/7/role', {role: 'vip'}, expect.any(Object));
    });
});
