import {mount} from '@vue/test-utils';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import Create from '@/pages/Parties/Moderation/Create.vue';
import Delete from '@/pages/Parties/Moderation/Delete.vue';
import Edit from '@/pages/Parties/Moderation/Edit.vue';
import Index from '@/pages/Parties/Moderation/Index.vue';

const inertia = vi.hoisted(() => ({post: vi.fn(), patch: vi.fn(), delete: vi.fn(), get: vi.fn()}));

vi.mock('@inertiajs/vue3', async () => {
    const {reactive} = await import('vue');
    return {
        Head: {render: () => null},
        Link: {props: ['href'], template: '<a :href="href"><slot /></a>'},
        router: {get: inertia.get},
        useForm: (data: Record<string, unknown>) =>
            reactive({
                ...data,
                errors: {},
                processing: false,
                post: inertia.post,
                patch: inertia.patch,
                delete: inertia.delete,
            }),
    };
});

vi.mock('@/layouts/AppLayout.vue', () => ({
    default: {template: '<div><slot name="breadcrumbs" /><slot /></div>'},
}));

const party = {code: 'ABCD', name: 'Party', canManage: true};
const playerState = {now: null, next: null, current: null, status: null};
const types = {mtName: 'Track Name', mtId: 'Track ID'};
const actions = {reject: 'Reject the request', flag: 'Flag for approval'};
const noFilters = {q: '', state: '', action: '', pattern: false};
const counts = {total: 2, on: 1, off: 1, flag: 1, reject: 1, pattern: 1};
const moderation = {
    id: 5,
    type: 'mtName',
    type_name: 'Track Name',
    action: 'flag',
    value: 'bad',
    enabled: true,
    regex: false,
    notes: 'n',
};

beforeEach(() => {
    vi.clearAllMocks();
});

describe('party moderation pages', () => {
    it('renders index rows with regex values as code', () => {
        const wrapper = mount(Index, {
            props: {
                party,
                playerState,
                moderationTabs: {flaggedCount: 0, aiReviewEnabled: false},
                filters: noFilters,
                counts,
                moderations: {
                    total: 2,
                    data: [moderation, {...moderation, id: 6, enabled: false, regex: true, value: '/x/'}],
                    current_page: 1,
                    last_page: 1,
                    prev_page_url: null,
                    next_page_url: null,
                    links: [],
                },
            },
        });
        expect(wrapper.findAll('tbody tr')).toHaveLength(2);
        expect(wrapper.find('a[href="/parties/ABCD/moderation/5/edit"]').exists()).toBe(true);
        expect(wrapper.find('.status-green').exists()).toBe(true);
        expect(wrapper.find('.status-secondary').exists()).toBe(true);
        expect(wrapper.find('.bg-yellow-lt').text()).toBe('Flag');
        expect(wrapper.find('code').text()).toBe('/x/');
        expect(wrapper.text()).toContain('Pattern');
        expect(wrapper.find('a[href="/parties/ABCD/moderation/5/delete"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="search-count"]').text()).toBe('2 Rules');
    });

    it('filters through the search bar and chips and offers one clear button when nothing matches', async () => {
        const empty = {
            data: [],
            total: 0,
            current_page: 1,
            last_page: 1,
            prev_page_url: null,
            next_page_url: null,
            links: [],
        };
        const wrapper = mount(Index, {
            props: {
                party,
                playerState,
                moderationTabs: {flaggedCount: 0, aiReviewEnabled: false},
                filters: {...noFilters, q: 'zzz', action: 'flag'},
                counts,
                moderations: empty,
            },
        });
        expect(wrapper.find('[data-test="empty-state"] .empty-title code').text()).toBe('zzz action:flag');
        expect(wrapper.find('[data-test="empty-state"]').text()).toContain('\u201czzz\u201d, Flag');
        expect(wrapper.findAll('[data-test="clear-filters"]')).toHaveLength(1);
        expect(wrapper.find('a[href="/parties/ABCD/moderation/create"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="searchbar"] a[href="/parties/ABCD/moderation/create"]').exists()).toBe(true);

        const chips = wrapper.findAll('[data-test="search-chip"]');
        expect(chips.map((chip) => chip.attributes('aria-pressed'))).toEqual([
            'false',
            'false',
            'true',
            'false',
            'false',
        ]);
        await chips[0]!.trigger('click');
        expect(inertia.get).toHaveBeenLastCalledWith(
            '/parties/ABCD/moderation',
            {q: 'zzz', state: 'on', action: 'flag'},
            {preserveState: true, replace: true},
        );
        await chips[2]!.trigger('click');
        await chips[4]!.trigger('click');
        expect(inertia.get).toHaveBeenLastCalledWith(
            '/parties/ABCD/moderation',
            {q: 'zzz', state: 'on', pattern: '1'},
            {preserveState: true, replace: true},
        );

        const input = wrapper.get('[data-test="search-input"]');
        expect((input.element as HTMLInputElement).value).toBe('zzz state:on match:pattern');
        await input.setValue('zzz action:reject state:off');
        await wrapper.get('form').trigger('submit');
        expect(inertia.get).toHaveBeenLastCalledWith(
            '/parties/ABCD/moderation',
            {q: 'zzz', state: 'off', action: 'reject'},
            {preserveState: true, replace: true},
        );
        expect(wrapper.findAll('[data-test="search-chip"]').map((chip) => chip.attributes('aria-pressed'))).toEqual([
            'false',
            'true',
            'false',
            'true',
            'false',
        ]);
        expect(input.attributes('placeholder')).toBe('Search rules\u2026 or try action:flag');
    });

    it('shows an empty state with an add rule action when there are no rules', () => {
        const wrapper = mount(Index, {
            props: {
                party,
                playerState,
                moderationTabs: {flaggedCount: 0, aiReviewEnabled: false},
                filters: noFilters,
                counts,
                moderations: {
                    data: [],
                    total: 0,
                    current_page: 1,
                    last_page: 1,
                    prev_page_url: null,
                    next_page_url: null,
                    links: [],
                },
            },
        });
        expect(wrapper.find('[data-test="empty-state"]').text()).toContain('No moderation rules');
        expect(wrapper.find('[data-test="empty-state"] a[href="/parties/ABCD/moderation/create"]').text()).toContain(
            'Add rule',
        );
        expect(wrapper.find('table').exists()).toBe(false);
    });

    it('posts the create form and shows track data when prefilled', async () => {
        const track = {
            id: 't1',
            name: 'Song',
            isrc: null,
            album: {id: 'a1', name: 'Album'},
            artists: [{id: 'r1', name: 'Artist'}],
        };
        const wrapper = mount(Create, {
            props: {
                party,
                playerState,
                moderationTabs: {flaggedCount: 0, aiReviewEnabled: false},
                types,
                actions,
                track,
                moderation: {...moderation, type: null, value: null, action: 'reject'},
            },
        });
        expect(wrapper.find('[data-test="track-data"]').text()).toContain('Artist');
        expect(wrapper.find('[data-test="track-data"]').text()).toContain('None');
        await wrapper.find('form').trigger('submit');
        expect(inertia.post).toHaveBeenCalledWith('/parties/ABCD/moderation');
    });

    it('omits track data without a track', () => {
        const wrapper = mount(Create, {props: {party, playerState, types, actions, track: null, moderation}});
        expect(wrapper.find('[data-test="track-data"]').exists()).toBe(false);
    });

    it('patches the edit form and links to delete', async () => {
        const wrapper = mount(Edit, {props: {party, playerState, types, actions, moderation}});
        expect(wrapper.find('h1').text()).toBe('Edit rule');
        expect(wrapper.find('a[href="/parties/ABCD/moderation/5/delete"]').exists()).toBe(true);
        await wrapper.find('form').trigger('submit');
        expect(inertia.patch).toHaveBeenCalledWith('/parties/ABCD/moderation/5');
    });

    it('confirms and deletes', async () => {
        const wrapper = mount(Delete, {props: {party, playerState, moderation}});
        expect(wrapper.text()).toContain('Delete this rule?');
        expect(wrapper.get('[data-test="delete-copy"]').text()).toContain('Track Name is bad');
        await wrapper.find('form').trigger('submit');
        expect(inertia.delete).toHaveBeenCalledWith('/parties/ABCD/moderation/5');
    });
});
