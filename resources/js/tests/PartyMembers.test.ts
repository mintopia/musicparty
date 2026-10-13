import {mount} from '@vue/test-utils';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import Edit from '@/pages/Parties/Members/Edit.vue';
import Index from '@/pages/Parties/Members/Index.vue';
import Show from '@/pages/Parties/Members/Show.vue';
import type {LegacyPartyState} from '@/types';

const inertia = vi.hoisted(() => ({get: vi.fn(), patch: vi.fn()}));

vi.mock('@inertiajs/vue3', async () => {
    const {reactive} = await import('vue');
    return {
        Head: {render: () => null},
        Link: {props: ['href'], template: '<a :href="href"><slot /></a>'},
        router: inertia,
        useForm: (data: Record<string, unknown>) =>
            reactive({...data, errors: {}, processing: false, patch: inertia.patch}),
    };
});
vi.mock('@/layouts/AppLayout.vue', () => ({
    default: {template: '<div><slot name="breadcrumbs" /><slot name="header" /><slot /></div>'},
}));
vi.mock('@/routes', () => ({home: () => ({url: '/'})}));
vi.mock('@/components/PartyPlayer.vue', () => ({default: {template: '<div data-test="party-player" />'}}));

const idle: LegacyPartyState = {now: null, next: null, current: null, status: null};
const party = {code: 'ABCD', name: 'Party', canManage: true};
const when = {iso: '2030-01-01 10:00:00', human: '3 hours ago'};
const roles = [
    {code: 'owner', name: 'Owner'},
    {code: 'banned', name: 'Banned'},
];
const member = {id: 5, nickname: 'alice', role: 'User', roleCode: 'user', createdAt: when};
const paginator = <T>(data: T[]) => ({
    data,
    current_page: 1,
    last_page: 1,
    prev_page_url: null,
    next_page_url: null,
    links: [],
});

beforeEach(() => {
    vi.clearAllMocks();
});

describe('party members', () => {
    it('lists members and keeps order and per_page on search', async () => {
        const wrapper = mount(Index, {
            props: {
                party,
                playerState: idle,
                roles,
                filters: {nickname: 'ali', role: 'banned', per_page: 5, order: 'role', order_direction: 'desc'},
                members: paginator([
                    {id: 5, nickname: 'alice', role: 'User', createdAt: when, url: '/parties/ABCD/users/5'},
                ]),
            },
        });

        expect(wrapper.find('a[href="/parties/ABCD/users/5"]').text()).toBe('alice');
        await wrapper.find('form').trigger('submit');
        expect(inertia.get).toHaveBeenCalledWith('/parties/ABCD/users', {
            order: 'role',
            order_direction: 'desc',
            per_page: 5,
            nickname: 'ali',
            role: 'banned',
        });
    });

    it('renders vote direction icons and the edit link', () => {
        const vote = (id: number, value: number) => ({
            id,
            songName: `Song ${id}`,
            albumName: 'Album',
            imageUrl: '/a.png',
            artists: ['A', 'B'],
            url: `/parties/ABCD/songs/${id}`,
            value,
            createdAt: when,
        });
        const wrapper = mount(Show, {
            props: {
                party,
                playerState: idle,
                member,
                filters: {},
                types: [{code: 'upvote', name: 'Upvote'}],
                votes: paginator([vote(1, 1), vote(2, -1)]),
            },
        });

        expect(wrapper.find('[data-test="vote-up"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="vote-down"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('A, B');
        expect(wrapper.find('a[href="/parties/ABCD/users/5/edit"]').exists()).toBe(true);
    });

    it('preselects the role, warns when editing self and patches', async () => {
        const wrapper = mount(Edit, {
            props: {party, playerState: idle, member: {...member, roleCode: 'banned'}, isSelf: true, roles},
        });

        expect(wrapper.find('[data-test="self-warning"]').exists()).toBe(true);
        expect((wrapper.find('input[value="banned"]').element as HTMLInputElement).checked).toBe(true);
        await wrapper.find('form').trigger('submit');
        expect(inertia.patch).toHaveBeenCalledWith('/parties/ABCD/users/5');
    });
});
