import {mount} from '@vue/test-utils';
import {afterEach, describe, expect, it, vi} from 'vitest';
import {defineComponent} from 'vue';
import {setEchoFactory, useEcho} from '@/composables/useEcho';

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({props: {reverb: {appKey: 'key'}}}),
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
});
