import {mount} from '@vue/test-utils';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {defineComponent} from 'vue';
import {setEchoFactory, useEcho} from '@/composables/useEcho';

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({props: {reverb: {appKey: 'key', host: 'example.test', port: 443, forceTLS: true}}}),
}));

type StateHandler = (states: {previous: string; current: string}) => void;

function fakeEcho() {
    const handlers: StateHandler[] = [];
    const echo = {
        private: vi.fn(),
        channel: vi.fn(),
        leave: vi.fn(),
        connector: {
            pusher: {
                connection: {
                    bind: vi.fn((_event: string, cb: StateHandler) => handlers.push(cb)),
                    unbind: vi.fn((_event: string, cb: StateHandler) => handlers.splice(handlers.indexOf(cb), 1)),
                },
            },
        },
    };
    return {echo, handlers};
}

afterEach(() => {
    setEchoFactory(null);
    vi.useRealTimers();
});

describe('useEcho', () => {
    it('leaves every joined channel once its last holder unmounts and shares one instance', () => {
        const {echo, handlers} = fakeEcho();
        const factory = vi.fn(() => echo);
        setEchoFactory(factory);

        const Host = defineComponent({
            setup() {
                const bus = useEcho();
                bus.privateChannel('party.ABC');
                bus.publicChannel('site');
                bus.onReconnect(() => {});
                return () => null;
            },
        });

        const first = mount(Host);
        const second = mount(Host);
        expect(factory).toHaveBeenCalledTimes(1);
        expect(factory).toHaveBeenCalledWith({appKey: 'key', host: 'example.test', port: 443, forceTLS: true});

        first.unmount();
        expect(echo.leave).not.toHaveBeenCalled();
        expect(handlers).toHaveLength(1);

        second.unmount();
        expect(echo.leave).toHaveBeenCalledTimes(2);
        expect(echo.leave).toHaveBeenCalledWith('party.ABC');
        expect(echo.leave).toHaveBeenCalledWith('site');
        expect(handlers).toHaveLength(0);
    });

    it('stops channel listeners when the component using useEcho unmounts', () => {
        const {echo} = fakeEcho();
        setEchoFactory(() => echo);
        const wrapper = mount(
            defineComponent({
                setup() {
                    useEcho().privateChannel('party.ABC');
                    return () => null;
                },
            }),
        );

        expect(echo.leave).not.toHaveBeenCalled();
        wrapper.unmount();
        expect(echo.leave).toHaveBeenCalledWith('party.ABC');
    });

    describe('reconnect jitter', () => {
        beforeEach(() => vi.useFakeTimers());
        afterEach(() => vi.useRealTimers());

        it('runs the callback once, only after a random 0-3s delay', () => {
            vi.spyOn(Math, 'random').mockReturnValue(0.5);
            const {echo, handlers} = fakeEcho();
            setEchoFactory(() => echo);
            const callback = vi.fn();
            mount(
                defineComponent({
                    setup() {
                        useEcho().onReconnect(callback);
                        return () => null;
                    },
                }),
            );

            handlers[0]({previous: 'connected', current: 'unavailable'});
            handlers[0]({previous: 'unavailable', current: 'connected'});
            vi.advanceTimersByTime(1499);
            expect(callback).not.toHaveBeenCalled();
            vi.advanceTimersByTime(1);
            expect(callback).toHaveBeenCalledTimes(1);
            vi.restoreAllMocks();
        });

        it('cancels a pending resync on unmount', () => {
            const {echo, handlers} = fakeEcho();
            setEchoFactory(() => echo);
            const callback = vi.fn();
            const wrapper = mount(
                defineComponent({
                    setup() {
                        useEcho().onReconnect(callback);
                        return () => null;
                    },
                }),
            );

            handlers[0]({previous: 'connected', current: 'unavailable'});
            handlers[0]({previous: 'unavailable', current: 'connected'});
            wrapper.unmount();
            vi.advanceTimersByTime(3000);
            expect(callback).not.toHaveBeenCalled();
        });
    });

    it('calls the reconnect hook only after a disconnect', () => {
        const {echo, handlers} = fakeEcho();
        setEchoFactory(() => echo);
        vi.useFakeTimers();
        const callback = vi.fn();

        mount(
            defineComponent({
                setup() {
                    useEcho().onReconnect(callback);
                    return () => null;
                },
            }),
        );

        handlers[0]({previous: 'connecting', current: 'connected'});
        expect(callback).not.toHaveBeenCalled();

        handlers[0]({previous: 'connected', current: 'unavailable'});
        handlers[0]({previous: 'unavailable', current: 'connected'});
        vi.advanceTimersByTime(3000);
        expect(callback).toHaveBeenCalledTimes(1);
    });

    describe('trackConnection', () => {
        const host = (target: {connected?: {value: boolean}}) =>
            mount(
                defineComponent({
                    setup() {
                        target.connected = useEcho().trackConnection();
                        return () => null;
                    },
                }),
            );

        it('does not flag the initial connection attempt, then flags a drop and recovery', () => {
            const {echo, handlers} = fakeEcho();
            setEchoFactory(() => echo);
            const target: {connected?: {value: boolean}} = {};
            host(target);
            expect(target.connected?.value).toBe(true);

            handlers[0]({previous: 'initialized', current: 'connecting'});
            expect(target.connected?.value).toBe(true);

            handlers[0]({previous: 'connecting', current: 'connected'});
            handlers[0]({previous: 'connected', current: 'connecting'});
            expect(target.connected?.value).toBe(false);

            handlers[0]({previous: 'connecting', current: 'connected'});
            expect(target.connected?.value).toBe(true);
        });

        it.each(['unavailable', 'failed', 'disconnected'])('is lost while %s', (state) => {
            const {echo, handlers} = fakeEcho();
            setEchoFactory(() => echo);
            const target: {connected?: {value: boolean}} = {};
            host(target);

            handlers[0]({previous: 'connecting', current: state});

            expect(target.connected?.value).toBe(false);
        });

        it('starts lost when the shared connection is already down, and unbinds on unmount', () => {
            const {echo, handlers} = fakeEcho();
            (echo.connector.pusher.connection as {state?: string}).state = 'unavailable';
            setEchoFactory(() => echo);
            const target: {connected?: {value: boolean}} = {};
            const wrapper = host(target);
            expect(target.connected?.value).toBe(false);

            wrapper.unmount();
            expect(handlers).toHaveLength(0);
        });
    });
});
