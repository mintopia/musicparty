import {flushPromises, mount} from '@vue/test-utils';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import Upcoming from '../components/Upcoming.vue';

const axiosRequest = vi.hoisted(() => vi.fn());
const loaderState = vi.hoisted(() => ({fail: false, retry: vi.fn()}));

vi.mock('axios', () => ({
    default: {request: axiosRequest, get: vi.fn(), isAxiosError: (error: {response?: unknown}) => 'response' in error},
}));
vi.mock('@ts-pro/vue-eternal-loading', () => ({
    VueEternalLoading: {
        template: '<div><slot v-if="fail" name="error" :retry="retry" /></div>',
        data: () => ({fail: loaderState.fail, retry: loaderState.retry}),
    },
}));
vi.mock('@inertiajs/vue3', () => ({usePage: () => ({props: {flash: {}, auth: {user: {id: 7}}}})}));
vi.mock('@/composables/useEcho', () => ({
    useEcho: () => ({onReconnect: () => undefined, privateChannel: () => ({listen: () => undefined})}),
}));

const song = (id: number, score = 1) => ({
    id,
    name: `Song ${id}`,
    artists: [{name: 'A'}],
    album: {name: 'Al', image_url: ''},
    length: 1000,
    score,
    vote: 0,
    user: 'u',
    user_id: 9,
    css_classes: '',
    fallback_name: null,
    created_at: `2030-01-0${id}T00:00:00Z`,
});

const mountQueue = (canManage = true) =>
    mount(Upcoming, {
        props: {party: 'ABC', canManage, initialState: [song(1, 3), song(2, 2), song(3, 1)]},
    });

const rowNames = (wrapper: ReturnType<typeof mountQueue>) =>
    wrapper.findAll('[data-test="track-row"]').map((row) => row.find('.q-title').text());

describe('Upcoming safe remove', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        axiosRequest.mockReset();
        loaderState.fail = false;
        loaderState.retry.mockReset();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('removes optimistically, names the track in an undo toast and deletes only after the undo window', async () => {
        axiosRequest.mockResolvedValue({});
        const wrapper = mountQueue();

        await wrapper.findAll('[data-test="remove"]')[1].trigger('click');

        expect(rowNames(wrapper)).toEqual(['Song 1', 'Song 3']);
        expect(wrapper.get('[role="alert"]').text()).toContain('Removed Song 2 from the Queue.');
        expect(axiosRequest).not.toHaveBeenCalled();

        await vi.advanceTimersByTimeAsync(8000);

        expect(axiosRequest).toHaveBeenCalledTimes(1);
        expect(axiosRequest.mock.calls[0][0].method).toBe('delete');
        expect(wrapper.find('[role="alert"]').exists()).toBe(false);
    });

    it('restores the row in place without calling the server when undo is used', async () => {
        const wrapper = mountQueue();

        await wrapper.findAll('[data-test="remove"]')[1].trigger('click');
        await wrapper.get('[data-test="toast-action"]').trigger('click');
        await vi.advanceTimersByTimeAsync(10000);

        expect(rowNames(wrapper)).toEqual(['Song 1', 'Song 2', 'Song 3']);
        expect(axiosRequest).not.toHaveBeenCalled();
        expect(wrapper.find('[role="alert"]').exists()).toBe(false);
    });

    it('commits an earlier pending removal when another row is removed', async () => {
        axiosRequest.mockResolvedValue({});
        const wrapper = mountQueue();

        await wrapper.findAll('[data-test="remove"]')[0].trigger('click');
        await wrapper.findAll('[data-test="remove"]')[0].trigger('click');

        expect(axiosRequest).toHaveBeenCalledTimes(1);
        expect(wrapper.get('[role="alert"]').text()).toContain('Song 2');
    });

    it('restores the row with a failure toast and retry when the server refuses', async () => {
        axiosRequest.mockRejectedValueOnce({response: {status: 500}}).mockResolvedValueOnce({});
        const wrapper = mountQueue();

        await wrapper.findAll('[data-test="remove"]')[1].trigger('click');
        await vi.advanceTimersByTimeAsync(8000);
        await flushPromises();

        expect(rowNames(wrapper)).toEqual(['Song 1', 'Song 2', 'Song 3']);
        expect(wrapper.findAll('[data-test="track-row"]')[1].classes()).toContain('mp-failed');
        expect(wrapper.get('[role="alert"]').text()).toContain("Couldn't remove Song 2. It is back in the Queue.");
        expect(wrapper.get('[role="alert"]').classes()).toContain('text-bg-danger');

        await wrapper.get('[data-test="toast-action"]').trigger('click');
        await flushPromises();

        expect(axiosRequest).toHaveBeenCalledTimes(2);
        expect(rowNames(wrapper)).toEqual(['Song 1', 'Song 3']);
    });

    it('commits a pending removal when the page is left', async () => {
        axiosRequest.mockResolvedValue({});
        const wrapper = mountQueue();

        await wrapper.findAll('[data-test="remove"]')[0].trigger('click');
        wrapper.unmount();

        expect(axiosRequest).toHaveBeenCalledTimes(1);
    });

    it('separates remove from the vote control and offers it in the phone menu with a reason', async () => {
        const wrapper = mountQueue();
        const row = wrapper.findAll('[data-test="track-row"]')[0];

        expect(row.find('.q-votes [data-test="remove"]').exists()).toBe(false);
        expect(row.find('.q-manage [data-test="remove"]').exists()).toBe(true);

        await row.get('[data-test="actions"]').trigger('click');

        expect(row.get('[data-test="actions"]').attributes('aria-label')).toBe('Actions for Song 1');
        expect(row.get('[data-test="actions-menu"]').text()).toContain('Guests can request it again');
    });

    it('hides every remove control from users who cannot manage', () => {
        const wrapper = mountQueue(false);

        expect(wrapper.find('[data-test="remove"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="actions"]').exists()).toBe(false);
    });

    it('offers a retry when the queue fails to load', async () => {
        loaderState.fail = true;
        const wrapper = mount(Upcoming, {props: {party: 'ABC', initialState: null}});

        expect(wrapper.get('[data-test="queue-error"]').text()).toContain('The Queue didn’t load');

        await wrapper.get('[data-test="retry"]').trigger('click');

        expect(loaderState.retry).toHaveBeenCalledTimes(1);
    });
});
