import {mount} from '@vue/test-utils';
import {describe, expect, it} from 'vitest';
import ModerationTabs from '@/components/ModerationTabs.vue';

function mountTabs(tabs: {flaggedCount: number; aiReviewEnabled: boolean}) {
    return mount(ModerationTabs, {props: {code: 'ABCD', tabs, active: 'flagged'}});
}

describe('ModerationTabs', () => {
    it('lists the tabs with their routes and flags the active one', () => {
        const wrapper = mountTabs({flaggedCount: 0, aiReviewEnabled: false});
        const links = wrapper.findAll('a').map((a) => [a.text(), a.attributes('href')]);

        expect(links).toEqual([
            ['Flagged Requests', '/parties/ABCD/flagged'],
            ['Rules', '/parties/ABCD/moderation'],
        ]);
        expect(wrapper.get('a.active').text()).toBe('Flagged Requests');
    });

    it('shows the flagged count only when there is something to review', () => {
        expect(mountTabs({flaggedCount: 0, aiReviewEnabled: false}).get('[data-test="tab-flagged"]').text()).toBe(
            'Flagged Requests',
        );
        expect(mountTabs({flaggedCount: 3, aiReviewEnabled: false}).get('[data-test="tab-flagged"]').text()).toBe(
            'Flagged Requests3',
        );
    });

    it('adds AI reviews only when the mod is enabled', () => {
        const wrapper = mountTabs({flaggedCount: 0, aiReviewEnabled: true});

        expect(wrapper.get('[data-test="tab-ai-reviews"]').attributes('href')).toBe('/parties/ABCD/ai-reviews');
    });
});
