import {mount} from '@vue/test-utils';
import {describe, expect, it, vi} from 'vitest';
import Shell from '@/pages/Shell.vue';

const props = vi.hoisted(() => ({
    value: {
        auth: {user: null as {nickname: string} | null, parties: [], can: {admin: false, createParty: false}},
        instanceName: 'Music Party',
        theme: {darkMode: false},
        site: {logoLight: null, favicon: null, terms: null, privacyPolicy: null},
        flash: {success: null, error: null, info: null, warning: null},
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: {render: () => null},
    Link: {props: ['href'], template: '<a :href="href"><slot /></a>'},
    usePage: () => ({props: props.value, url: '/ui/shell'}),
}));

describe('Shell page', () => {
    it('shows the instance name and guest state', () => {
        const wrapper = mount(Shell);

        expect(wrapper.get('h1').text()).toBe('Music Party');
        expect(wrapper.text()).toContain('Guest');
    });

    it('shows the signed-in nickname', () => {
        props.value.auth.user = {nickname: 'Jess'};

        expect(mount(Shell).text()).toContain('Signed in as Jess');
    });
});
