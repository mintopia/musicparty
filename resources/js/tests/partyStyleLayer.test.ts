import {mount} from '@vue/test-utils';
import {describe, expect, it} from 'vitest';
import EmptyState from '../components/EmptyState.vue';
import SectionHead from '../components/SectionHead.vue';
import StageSurface from '../components/StageSurface.vue';
import StatePill from '../components/StatePill.vue';
import SuccessMark from '../components/SuccessMark.vue';

describe('party style layer', () => {
    it.each([
        ['playing', 'Playing'],
        ['paused', 'Paused'],
        ['stale', 'Player stale'],
        ['idle', 'Nothing playing'],
    ] as const)('renders the %s state pill', (state, label) => {
        const pill = mount(StatePill, {props: {state}});
        expect(pill.classes()).toContain(`is-${state}`);
        expect(pill.text()).toBe(label);
    });

    it('shows equaliser bars only while playing', () => {
        expect(mount(StatePill, {props: {state: 'playing'}}).find('.mp-eq').exists()).toBe(true);
        expect(mount(StatePill, {props: {state: 'paused'}}).find('.mp-eq').exists()).toBe(false);
    });

    it('renders a section head with a count and actions', () => {
        const head = mount(SectionHead, {props: {title: 'Flagged', count: 3, attention: true}, slots: {default: '<button>Clear</button>'}});
        expect(head.find('h2').text()).toBe('Flagged');
        expect(head.find('.mp-count').classes()).toContain('is-attention');
        expect(head.find('.mp-section-actions button').exists()).toBe(true);
        expect(mount(SectionHead, {props: {title: 'Queue'}}).find('.mp-count').exists()).toBe(false);
    });

    it('marks an empty state as all clear', () => {
        const empty = mount(EmptyState, {props: {title: 'All clear', icon: 'ti-check', allClear: true}});
        expect(empty.classes()).toContain('mp-all-clear');
        expect(mount(EmptyState, {props: {title: 'Nothing'}}).classes()).not.toContain('mp-all-clear');
    });

    it('sets the stage art image', () => {
        const stage = mount(StageSurface, {props: {image: '/art/a.jpg'}, slots: {default: '<h1>Hi</h1>'}});
        expect(stage.attributes('style')).toContain('--np-image');
        expect(stage.find('h1').exists()).toBe(true);
    });

    it('renders the success mark', () => {
        expect(mount(SuccessMark).find('.ti-check').exists()).toBe(true);
    });
});
