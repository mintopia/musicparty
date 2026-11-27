import {mount} from '@vue/test-utils';
import {describe, expect, it} from 'vitest';
import PartyList from '@/components/PartyList.vue';

const row = {
    code: 'KXQB',
    name: 'Coyote LAN 26',
    role: 'Moderator',
    roleCode: 'moderator',
    playback: 'idle' as const,
    detail: null,
    cover: null,
    canManage: false,
    canModerate: true,
    url: '/parties/KXQB/dashboard',
};

describe('PartyList', () => {
    it('opens the dashboard for moderators', () => {
        const wrapper = mount(PartyList, {props: {parties: [row]}, global: {stubs: {Link: {template: '<a><slot /></a>'}}}});

        expect(wrapper.text()).toContain('Open Dashboard');
    });

    it('opens the queue for guests', () => {
        const wrapper = mount(PartyList, {
            props: {parties: [{...row, canModerate: false}]},
            global: {stubs: {Link: {template: '<a><slot /></a>'}}},
        });

        expect(wrapper.text()).toContain('Open Queue');
    });
});
