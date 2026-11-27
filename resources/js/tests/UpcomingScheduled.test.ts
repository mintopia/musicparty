import {mount} from '@vue/test-utils';
import {describe, expect, it, vi} from 'vitest';
import Upcoming from '../components/Upcoming.vue';

vi.mock('@ts-pro/vue-eternal-loading', () => ({VueEternalLoading: {template: '<div />'}}));
vi.mock('@inertiajs/vue3', () => ({usePage: () => ({props: {flash: {}, auth: {user: {id: 7}}}})}));
vi.mock('@/composables/useEcho', () => ({
    useEcho: () => ({onReconnect: () => undefined, privateChannel: () => ({listen: () => undefined})}),
}));

const song = (id: number, extra: Record<string, unknown> = {}) => ({
    id,
    name: `Song ${id}`,
    artists: [{name: 'A'}],
    album: {name: 'Al', image_url: ''},
    length: 1000,
    score: 1,
    vote: 0,
    user: 'u',
    css_classes: '',
    fallback_name: null,
    created_at: '2030-01-01T00:00:00Z',
    ...extra,
});

describe('Upcoming scheduled time', () => {
    it('shows the scheduled time in the party timezone only for scheduled requests', async () => {
        const wrapper = mount(Upcoming, {
            props: {
                party: 'ABC',
                timezone: 'America/New_York',
                initialState: [song(1, {scheduled_for: '2030-01-02T01:00:00+00:00'}), song(2)],
            },
        });

        await wrapper.vm.$nextTick();
        const labels = wrapper.findAll('[data-test="scheduled-time"]');
        expect(labels).toHaveLength(1);
        expect(labels[0].text()).toMatch(/Jan 1, 2030.*8:00/);
    });
});

describe('Upcoming decoration', () => {
    it('applies decoration class once and shows displayName for fallback tracks', async () => {
        const wrapper = mount(Upcoming, {
            props: {
                party: 'ABC',
                timezone: 'UTC',
                initialState: [
                    song(1, {
                        user: null,
                        fallback_name: 'Fallback',
                        css_classes: 'old mod-row',
                        decoration: {displayName: 'Mod Name', class: 'mod-row extra'},
                    }),
                    song(2, {user: null, fallback_name: 'Plain', decoration: null}),
                ],
            },
        });

        await wrapper.vm.$nextTick();
        const rows = wrapper.findAll('[data-test="track-row"]');
        expect(rows[0].classes().filter((c) => c === 'mod-row')).toHaveLength(1);
        expect(rows[0].classes()).toEqual(expect.arrayContaining(['old', 'extra']));
        expect(rows[0].text()).toContain('Mod Name');
        expect(rows[0].text()).not.toContain('Fallback');
        expect(rows[1].text()).toContain('Plain');
    });
});

describe('Upcoming guest rows', () => {
    it('uses the shared vote control, marks own requests and credits the Fallback Playlist', async () => {
        const wrapper = mount(Upcoming, {
            props: {
                party: 'ABC',
                canDownvote: true,
                initialState: [
                    song(1, {user_id: 7, user: 'me'}),
                    song(2, {user: null, user_id: null}),
                    song(3, {user_id: 9}),
                ],
            },
        });

        await wrapper.vm.$nextTick();
        expect(wrapper.findAllComponents({name: 'VoteControl'})).toHaveLength(3);
        expect(wrapper.findAll('[data-test="your-request"]')).toHaveLength(1);
        expect(wrapper.text()).toContain('Requested by you');
        expect(wrapper.text()).toContain('From the party’s Fallback Playlist');
        expect(wrapper.text()).not.toContain('From Fallback Playlist');
    });
});

describe('Upcoming layout', () => {
    it('numbers rows, keeps artist and requester on one line, and offers search when empty', async () => {
        const wrapper = mount(Upcoming, {props: {party: 'ABC', initialState: [song(1), song(2)]}});

        await wrapper.vm.$nextTick();
        expect(wrapper.findAll('.q-pos').map((n) => n.text())).toEqual(['1', '2']);
        expect(wrapper.get('.q-by').text()).toContain('A · Requested by u');

        const empty = mount(Upcoming, {props: {party: 'ABC', initialState: []}});
        await empty.vm.$nextTick();
        expect(empty.find('[data-test="queue-card"]').exists()).toBe(false);
    });
});
