import {mount} from '@vue/test-utils';
import {describe, expect, it} from 'vitest';
import VoteControl from '@/components/VoteControl.vue';

describe('VoteControl', () => {
    it('offers a labelled like button with pressed state when downvotes are off', async () => {
        const wrapper = mount(VoteControl, {props: {score: 3, vote: 1, label: 'Levels'}});
        const heart = wrapper.get('[data-test=heart]');

        expect(heart.element.tagName).toBe('BUTTON');
        expect(heart.attributes('aria-label')).toBe('Like Levels');
        expect(heart.attributes('aria-pressed')).toBe('true');
        expect(wrapper.get('[data-test=score]').text()).toBe('3');
        await heart.trigger('click');
        expect(wrapper.emitted('vote')).toEqual([[0]]);
    });

    it('offers up and down votes that toggle their own state', async () => {
        const wrapper = mount(VoteControl, {props: {score: 0, vote: -1, canDownvote: true}});

        expect(wrapper.get('[data-test=upvote]').attributes('aria-pressed')).toBe('false');
        expect(wrapper.get('[data-test=downvote]').attributes('aria-pressed')).toBe('true');
        await wrapper.get('[data-test=upvote]').trigger('click');
        await wrapper.get('[data-test=downvote]').trigger('click');
        expect(wrapper.emitted('vote')).toEqual([[1], [0]]);
    });
});
