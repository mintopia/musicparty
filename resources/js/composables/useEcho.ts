import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import {usePage} from '@inertiajs/vue3';
import {onBeforeUnmount} from 'vue';
import type {SharedProps} from '@/types';

type EchoLike = {
    private(name: string): unknown;
    channel(name: string): unknown;
    leave(name: string): void;
    connector: {pusher: {connection: PusherConnection}};
};

type PusherConnection = {
    state?: string;
    bind(event: string, cb: (states: {previous: string; current: string}) => void): void;
    unbind(event: string, cb: (states: {previous: string; current: string}) => void): void;
};

export type EchoFactory = (appKey: string | null) => EchoLike;

const defaultFactory: EchoFactory = (appKey) => {
    const forceTLS = window.location.protocol === 'https:';

    return new Echo({
        broadcaster: 'reverb',
        key: appKey ?? '',
        Pusher,
        wsHost: window.location.hostname,
        wsPort: Number(window.location.port) || (forceTLS ? 443 : 80),
        wssPort: Number(window.location.port) || 443,
        forceTLS,
        enabledTransports: ['ws', 'wss'],
    } as ConstructorParameters<typeof Echo>[0]) as unknown as EchoLike;
};

let factory: EchoFactory = defaultFactory;
let instance: EchoLike | null = null;
const channelRefs = new Map<string, number>();

export function setEchoFactory(next: EchoFactory | null): void {
    factory = next ?? defaultFactory;
    instance = null;
    channelRefs.clear();
}

export function useEcho() {
    const page = usePage<SharedProps>();
    instance ??= factory(page.props.reverb.appKey);
    const echo = instance;
    const joined = new Set<string>();

    function join(name: string): void {
        if (joined.has(name)) {
            return;
        }
        joined.add(name);
        channelRefs.set(name, (channelRefs.get(name) ?? 0) + 1);
    }
    const reconnectHandlers: Array<(states: {previous: string; current: string}) => void> = [];
    const connection = echo.connector.pusher.connection;

    function privateChannel(name: string) {
        join(name);
        return echo.private(name);
    }

    function publicChannel(name: string) {
        join(name);
        return echo.channel(name);
    }

    function onReconnect(callback: () => void): void {
        let wasDisconnected = false;
        const handler = ({current}: {previous: string; current: string}) => {
            if (current !== 'connected') {
                wasDisconnected = true;
            } else if (wasDisconnected) {
                wasDisconnected = false;
                callback();
            }
        };
        reconnectHandlers.push(handler);
        connection.bind('state_change', handler);
    }

    onBeforeUnmount(() => {
        joined.forEach((name) => {
            const remaining = (channelRefs.get(name) ?? 1) - 1;
            if (remaining > 0) {
                channelRefs.set(name, remaining);
                return;
            }
            channelRefs.delete(name);
            echo.leave(name);
        });
        joined.clear();
        reconnectHandlers.forEach((handler) => connection.unbind('state_change', handler));
        reconnectHandlers.length = 0;
    });

    return {privateChannel, publicChannel, onReconnect};
}
