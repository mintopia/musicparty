import {mount} from '@vue/test-utils';
import {afterEach, describe, expect, it, vi} from 'vitest';
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

afterEach(() => setEchoFactory(null));

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

    it('calls the reconnect hook only after a disconnect', () => {
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

        handlers[0]({previous: 'connecting', current: 'connected'});
        expect(callback).not.toHaveBeenCalled();

        handlers[0]({previous: 'connected', current: 'unavailable'});
        handlers[0]({previous: 'unavailable', current: 'connected'});
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
