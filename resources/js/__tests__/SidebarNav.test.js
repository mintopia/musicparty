import {beforeEach, describe, expect, it, vi} from 'vitest';
import {mount} from '@vue/test-utils';

const page = vi.hoisted(() => ({props: {}, url: '/'}));
const post = vi.hoisted(() => vi.fn());

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => page,
    router: {post},
    Link: {props: ['href'], template: '<a :href="href"><slot /></a>'},
}));

import SidebarNav from '../Components/SidebarNav.vue';

beforeEach(() => {
    post.mockReset();
    page.props = {appName: 'Music Party', auth: {user: {is_admin: false}}, parties: []};
});

describe('SidebarNav', () => {
    it('logs out with a POST to /logout', async () => {
        const wrapper = mount(SidebarNav);

        await wrapper.find('[data-testid="logout"]').trigger('click');

        expect(post).toHaveBeenCalledWith('/logout');
    });
});
