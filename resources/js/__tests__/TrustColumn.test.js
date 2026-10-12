import {mount} from '@vue/test-utils';
import {describe, expect, it} from 'vitest';

import ModSlot from '../Components/ModSlot.vue';
import {createModSlots} from '../lib/modSlots';
import trustScore from '../mods/trust-score/index.js';

const registry = createModSlots({'../mods/trust-score/index.js': {default: trustScore}});

const mountColumn = (enabled, trust) => mount(ModSlot, {
    props: {registry, name: 'members.columns', member: {id: 4}, trust},
    global: {provide: {enabledMods: enabled}},
});

describe('trust-score members column', () => {
    it('renders the score to two decimals when the Mod is enabled', () => {
        expect(mountColumn(['trust-score'], 0.8567).get('[data-testid=member-trust]').text()).toBe('0.86');
    });

    it('renders a dash when the member has no score', () => {
        expect(mountColumn(['trust-score'], null).get('[data-testid=member-trust]').text()).toBe('-');
    });

    it('renders nothing when the Mod is not enabled', () => {
        expect(mountColumn([], 0.5).find('[data-testid=member-trust]').exists()).toBe(false);
    });
});
