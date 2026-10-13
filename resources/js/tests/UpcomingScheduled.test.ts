import {mount} from '@vue/test-utils';
import {describe, expect, it, vi} from 'vitest';
import Upcoming from '../components/Upcoming.vue';

vi.mock('@ts-pro/vue-eternal-loading', () => ({VueEternalLoading: {template: '<div />'}}));

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
    created_at: '2030-01-01T00:00:00Z',
    ...extra,
});

describe('Upcoming scheduled time', () => {
    it('shows the scheduled time in the party timezone only for scheduled requests', async () => {
        (window as unknown as {Echo: unknown}).Echo = {private: () => ({listen: () => {}})};
        const wrapper = mount(Upcoming, {
            props: {
                party: 'ABC',
                timezone: 'America/New_York',
                initialstate: JSON.stringify([song(1, {scheduled_for: '2030-01-02T01:00:00+00:00'}), song(2)]),
            },
        });

        await wrapper.vm.$nextTick();
        const labels = wrapper.findAll('.scheduled-time');
        expect(labels).toHaveLength(1);
        expect(labels[0].text()).toMatch(/Jan 1, 2030.*8:00/);
    });
});
