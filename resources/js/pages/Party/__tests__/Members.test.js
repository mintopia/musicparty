import {mount} from '@vue/test-utils';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';

const router = vi.hoisted(() => ({get: vi.fn(), put: vi.fn(), delete: vi.fn()}));

vi.mock('@inertiajs/vue3', () => ({
    Head: {template: '<span />'},
    Link: {props: ['href'], template: '<a :href="href"><slot /></a>'},
    router,
    usePage: () => ({url: '/parties/ABCD/members?sort=role&direction=desc', props: {errors: {}}}),
}));

import Members from '../Members.vue';

const member = (overrides = {}) => ({id: 7, nickname: 'Alex', avatar: null, role: 'guest', banned: false, isYou: false, joinedAt: '2026-10-01T10:00:00Z', ...overrides});
const mountMembers = (members = [member()]) => mount(Members, {
    props: {
        party: {code: 'ABCD', name: 'Party'},
        members: {data: members, total: members.length, from: 1, to: members.length, last_page: 1, links: []},
        filters: {search: '', role: '', sort: 'role', direction: 'desc'},
        abilities: {canChangeRoles: true, canBan: true},
    },
});

describe('Party Members', () => {
    beforeEach(() => vi.clearAllMocks());
    afterEach(() => vi.useRealTimers());

    it('changes a role through the custom select', async () => {
        const w = mountMembers();
        const select = w.get('[data-testid="member-row"] [data-testid="select-field"]');
        await select.get('button').trigger('click');
        const vip = select.findAll('[role=option]').find((option) => option.text() === 'VIP');
        await vip.trigger('mousedown');
        expect(router.put).toHaveBeenCalledWith('/parties/ABCD/members/7/role', {role: 'vip'}, expect.any(Object));
    });

    it('does not offer role or ban controls for the host', () => {
        const w = mountMembers([member({role: 'host'})]);
        expect(w.find('[data-testid="member-row"] [data-testid="select-field"]').exists()).toBe(false);
        expect(w.find('[data-testid="member-ban"]').exists()).toBe(false);
    });

    it('bans and unbans', async () => {
        const w = mountMembers([member(), member({id: 8, banned: true})]);
        await w.get('[data-testid="member-ban"]').trigger('click');
        expect(router.put).toHaveBeenCalledWith('/parties/ABCD/members/7/ban', {}, expect.any(Object));
        await w.get('[data-testid="member-unban"]').trigger('click');
        expect(router.delete).toHaveBeenCalledWith('/parties/ABCD/members/8/ban', expect.any(Object));
    });

    it('debounces search and keeps the current sort', async () => {
        vi.useFakeTimers();
        const w = mountMembers();
        await w.get('[data-testid="members-search"]').setValue('al');
        expect(router.get).not.toHaveBeenCalled();
        vi.advanceTimersByTime(300);
        expect(router.get).toHaveBeenCalledWith('/parties/ABCD/members', {search: 'al', sort: 'role', direction: 'desc'}, expect.objectContaining({preserveState: true, preserveScroll: true}));
    });

    it('filters by role', async () => {
        const w = mountMembers();
        const filter = w.get('#members-role-filter').element.closest('[data-testid="select-field"]');
        await w.get('#members-role-filter').trigger('click');
        const option = [...filter.querySelectorAll('[role=option]')].find((el) => el.textContent.trim() === 'VIP');
        option.dispatchEvent(new MouseEvent('mousedown', {bubbles: true}));
        expect(router.get).toHaveBeenCalledWith('/parties/ABCD/members', {role: 'vip', sort: 'role', direction: 'desc'}, expect.any(Object));
    });

    it('renders sortable headers using sort and direction params', () => {
        const w = mountMembers();
        const href = w.get('[data-sort="nickname"]').attributes('href');
        expect(href).toBe('/parties/ABCD/members?sort=nickname&direction=asc');
        expect(w.get('[data-sort="role"]').attributes('href')).toContain('direction=asc');
    });
});
