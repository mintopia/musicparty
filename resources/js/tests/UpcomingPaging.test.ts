import {flushPromises, mount} from '@vue/test-utils';
import {describe, expect, it, vi} from 'vitest';
import Upcoming from '../components/Upcoming.vue';

const axiosGet = vi.hoisted(() => vi.fn());

vi.mock('axios', () => ({
    default: {request: vi.fn(), get: axiosGet, isAxiosError: () => false},
}));
vi.mock('@ts-pro/vue-eternal-loading', () => ({
    VueEternalLoading: {
        props: ['load'],
        template: '<div data-test="loader"></div>',
    },
}));
vi.mock('@inertiajs/vue3', () => ({usePage: () => ({props: {flash: {}, auth: {user: {id: 7}}}})}));
vi.mock('@/composables/useEcho', () => ({
    useEcho: () => ({onReconnect: () => undefined, privateChannel: () => ({listen: () => undefined})}),
}));

const song = (id: number) => ({
    id,
    name: `Song ${id}`,
    artists: [{name: 'A'}],
    album: {name: 'Al', image_url: ''},
    length: 1000,
    score: 1,
    vote: 0,
    user: 'u',
    user_id: 9,
    css_classes: '',
    fallback_name: null,
    created_at: `2030-01-01T00:00:${String(id).padStart(2, '0')}Z`,
});

const songs = (count: number) => Array.from({length: count}, (_, index) => song(index + 1));

const mountQueue = (initialState: ReturnType<typeof songs> | null) =>
    mount(Upcoming, {props: {party: 'ABC', initialState}});

describe('Upcoming paging', () => {
    it('renders no loader when the first page is shorter than the page size', () => {
        const wrapper = mountQueue(songs(8));

        expect(wrapper.findAll('[data-test="track-row"]')).toHaveLength(8);
        expect(wrapper.find('[data-test="loader"]').exists()).toBe(false);
    });

    it('shows the empty state without a loader when the first page is empty', () => {
        const wrapper = mountQueue([]);

        expect(wrapper.find('[data-test="queue-empty"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="loader"]').exists()).toBe(false);
    });

    it('keeps the loader for a full first page and requests the next page', async () => {
        axiosGet.mockResolvedValue({data: {data: []}});
        const wrapper = mountQueue(songs(20));

        expect(wrapper.find('[data-test="loader"]').exists()).toBe(true);

        const loaded = vi.fn();
        const loader = wrapper.getComponent('[data-test="loader"]') as unknown as {
            vm: {$props: {load: (action: unknown) => Promise<void>}};
        };
        const load = loader.vm.$props.load;
        await load({
            loaded,
            error: vi.fn(),
        });
        await flushPromises();

        expect(axiosGet.mock.calls[0][1]).toEqual({params: {page: 2}});
        expect(loaded).toHaveBeenCalledWith(0, 20);
    });

    it('keeps the loader when no initial state was supplied', () => {
        const wrapper = mountQueue(null);

        expect(wrapper.find('[data-test="loader"]').exists()).toBe(true);
    });
});
