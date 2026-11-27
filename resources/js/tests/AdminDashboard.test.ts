import {mount} from '@vue/test-utils';
import {describe, expect, it, vi} from 'vitest';
import Dashboard from '@/pages/Admin/Dashboard.vue';
import type {AdminDashboardProps} from '@/types';

vi.mock('@inertiajs/vue3', () => ({
    Head: {render: () => null},
    Link: {props: ['href'], template: '<a :href="href"><slot /></a>'},
    usePoll: vi.fn(),
}));

vi.mock('@/layouts/AppLayout.vue', () => ({
    default: {template: '<div><slot name="breadcrumbs" /><slot /></div>'},
}));

vi.mock('@/lib/breadcrumbs', () => ({adminCrumbs: () => []}));

const base: AdminDashboardProps = {
    generatedAt: new Date().toISOString(),
    liveParties: [],
    quietFor: null,
    attention: [],
    health: [{key: 'redis', label: 'Redis', status: 'ok', detail: 'Connected.', link: null}],
    activity: {stats: {signUpsThisWeek: 1, partiesThisWeek: 2, playedToday: 3, users: 4}, recent: []},
    links: {pulse: '/pulse', horizon: '/horizon', createParty: '/parties/create'},
};

describe('admin dashboard', () => {
    it('shows the no-parties and all-clear states', () => {
        const wrapper = mount(Dashboard, {props: {...base, quietFor: '3 days'}});
        expect(wrapper.text()).toContain('No parties are live');
        expect(wrapper.text()).toContain('Nobody has been on this instance for 3 days.');
        expect(wrapper.find('[data-test="create-party"]').attributes('href')).toBe('/parties/create');
        expect(wrapper.text()).toContain('Nothing needs attention');
        expect(wrapper.text()).toContain('Nothing yet this week.');
    });

    it('renders live parties, attention rows with their fix, and health', () => {
        const wrapper = mount(Dashboard, {
            props: {
                ...base,
                liveParties: [
                    {
                        code: 'KXQB',
                        name: 'Coyote LAN 26',
                        url: '/parties/KXQB/dashboard',
                        owner: 'Jess',
                        state: 'error',
                        stateLabel: 'Re-link needed',
                        stateDetail: null,
                        track: null,
                        guestsOnline: null,
                        requestsWaiting: 12,
                        flagged: 2,
                    },
                ],
                attention: [
                    {
                        key: 'failed-jobs',
                        severity: 'warn',
                        title: '3 jobs failed in the last hour',
                        detail: 'x',
                        action: {label: 'Open Horizon', url: '/horizon/failed'},
                    },
                ],
            },
        });
        expect(wrapper.findAll('[data-test="party-row"]')).toHaveLength(1);
        expect(wrapper.find('[data-test="party-row"]').text()).toContain('Re-link needed');
        expect(wrapper.find('[data-test="party-row"]').text()).toContain('Nothing to play');
        expect(wrapper.find('[data-test="attention-item"] a').attributes('href')).toBe('/horizon/failed');
        expect(wrapper.find('[data-test="section-count"], .mp-count.is-attention').text()).toBe('1');
        expect(wrapper.find('[data-test="health-item"]').text()).toContain('Redis');
    });
});
