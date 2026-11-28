import {flushPromises, mount} from '@vue/test-utils';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import Upcoming from '../components/Upcoming.vue';
import {setEchoFactory} from '@/composables/useEcho';

const axiosGet = vi.hoisted(() => vi.fn());

vi.mock('axios', () => ({
    default: {request: vi.fn(), get: axiosGet, isAxiosError: () => false},
}));
vi.mock('@ts-pro/vue-eternal-loading', () => ({
    VueEternalLoading: {props: ['load'], template: '<div data-test="loader"></div>'},
}));
vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({
        props: {flash: {}, auth: {user: {id: 7}}, reverb: {appKey: 'k', host: 'h', port: 443, forceTLS: true}},
    }),
}));

type StateHandler = (states: {previous: string; current: string}) => void;

describe('Upcoming reconnect', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => {
        setEchoFactory(null);
        vi.useRealTimers();
        vi.restoreAllMocks();
        axiosGet.mockReset();
    });

    it('waits out the jitter, then refetches page 1 only', async () => {
        vi.spyOn(Math, 'random').mockReturnValue(0.5);
        const handlers: StateHandler[] = [];
        setEchoFactory(() => ({
            private: () => ({listen: () => undefined}),
            channel: () => ({listen: () => undefined}),
            leave: () => undefined,
            connector: {
                pusher: {
                    connection: {bind: (_e: string, cb: StateHandler) => handlers.push(cb), unbind: () => undefined},
                },
            },
        }));
        axiosGet.mockResolvedValue({data: {data: []}});
        mount(Upcoming, {props: {party: 'ABC', initialState: []}});

        handlers.forEach((h) => h({previous: 'connected', current: 'unavailable'}));
        handlers.forEach((h) => h({previous: 'unavailable', current: 'connected'}));
        vi.advanceTimersByTime(1499);
        await flushPromises();
        expect(axiosGet).not.toHaveBeenCalled();

        vi.advanceTimersByTime(1);
        await flushPromises();
        expect(axiosGet).toHaveBeenCalledTimes(1);
        expect(axiosGet.mock.calls[0][1]).toEqual({params: {page: 1}});
    });
});
