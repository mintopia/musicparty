import {mount} from '@vue/test-utils';
import {computed, defineComponent, h} from 'vue';
import {describe, expect, it, vi} from 'vitest';

vi.mock('@inertiajs/vue3', () => ({router: {put: vi.fn(), delete: vi.fn()}}));

import ModSlot from '../Components/ModSlot.vue';
import QueueList from '../Components/QueueList.vue';
import {createModSlots} from '../lib/modSlots';

const Probe = (text) => defineComponent({
    props: {item: {type: Object, default: null}},
    setup: (props) => () => h('span', {'data-testid': 'probe'}, `${text}${props.item ? `:${props.item.id}` : ''}`),
});

const registry = createModSlots({
    '../mods/alpha/index.js': {default: {slots: {'queue-item': Probe('alpha'), 'unknown-slot': Probe('nope')}}},
    '../mods/beta/index.js': {default: {slots: {'queue-item': Probe('beta'), settings: Probe('beta-settings')}}},
    '../mods/broken/index.js': {default: {}},
});

const mountSlot = (enabled, props = {}) => mount(ModSlot, {props: {registry, ...props}, global: {provide: {enabledMods: enabled}}});

describe('ModSlot', () => {
    it('renders only contributions from enabled Mods', () => {
        const w = mountSlot(['alpha'], {name: 'queue-item'});
        expect(w.findAll('[data-testid=probe]').map((n) => n.text())).toEqual(['alpha']);
    });

    it('renders nothing when the Mod is not enabled', () => {
        expect(mountSlot([], {name: 'queue-item'}).find('[data-testid=probe]').exists()).toBe(false);
        expect(mountSlot(['gamma'], {name: 'queue-item'}).find('[data-testid=probe]').exists()).toBe(false);
    });

    it('renders every enabled Mod and reacts to the enabled list', () => {
        const w = mountSlot(computed(() => ['alpha', 'beta']), {name: 'queue-item'});
        expect(w.findAll('[data-testid=probe]').map((n) => n.text())).toEqual(['alpha', 'beta']);
    });

    it('ignores unknown slot names', () => {
        expect(mountSlot(['alpha', 'beta'], {name: 'unknown-slot'}).find('[data-testid=probe]').exists()).toBe(false);
        expect(registry.contributions('unknown-slot', ['alpha'])).toEqual([]);
    });

    it('keeps slots separate', () => {
        const w = mountSlot(['alpha', 'beta'], {name: 'settings'});
        expect(w.findAll('[data-testid=probe]').map((n) => n.text())).toEqual(['beta-settings']);
    });

    it('passes props through', () => {
        const w2 = mount(ModSlot, {props: {registry, name: 'queue-item'}, attrs: {item: {id: 9}}, global: {provide: {enabledMods: ['alpha']}}});
        expect(w2.get('[data-testid=probe]').text()).toBe('alpha:9');
    });

    it('defaults to no Mods when nothing is provided', () => {
        expect(mount(ModSlot, {props: {registry, name: 'queue-item'}}).find('[data-testid=probe]').exists()).toBe(false);
    });
});

describe('queue-item slot in QueueList', () => {
    it('hands each row its item', () => {
        const track = {title: 'T', artists: ['A'], artwork_url: null, duration_ms: 1000};
        const queue = [1, 2].map((id) => ({id, track, status: 'queued', score: 0, my_vote: 0, requested_by: {name: 'A'}}));
        const ModSlotStub = defineComponent({
            props: {name: String},
            inheritAttrs: false,
            setup: (props, {attrs}) => () => h('i', {'data-testid': 'slot', 'data-slot': props.name}, String(attrs.item.id)),
        });
        const w = mount(QueueList, {props: {queue, partyCode: 'ABCD'}, global: {stubs: {ModSlot: ModSlotStub}}});
        expect(w.findAll('[data-testid=slot]').map((n) => [n.attributes('data-slot'), n.text()])).toEqual([['queue-item', '1'], ['queue-item', '2']]);
    });
});
