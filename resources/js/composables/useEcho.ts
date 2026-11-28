import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import {usePage} from '@inertiajs/vue3';
import {onBeforeUnmount, ref, type Ref} from 'vue';
import {echoOptions} from '@/lib/realtime';
import type {RealtimeConfig, SharedProps} from '@/types';

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

export const RECONNECT_JITTER_MS = 3000;

const LOST_STATES = ['unavailable', 'failed', 'disconnected'];

export type EchoFactory = (config: RealtimeConfig) => EchoLike;

const defaultFactory: EchoFactory = (config) =>
    new Echo({...echoOptions(config), Pusher} as ConstructorParameters<typeof Echo>[0]) as unknown as EchoLike;

let factory: EchoFactory = defaultFactory;
let instance: EchoLike | null = null;
const channelRefs = new Map<string, number>();

export function setEchoFactory(next: EchoFactory | null): void {
    factory = next ?? defaultFactory;
    instance = null;
    channelRefs.clear();
}

/**
 * Echo listeners on shared channels rely on this composable detaching them on unmount
 * (finding #69, left as is); a persistent layout would have to detach its own.
 */
export function useEcho() {
    const page = usePage<SharedProps>();
    instance ??= factory(page.props.reverb);
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

    function trackConnection(): Ref<boolean> {
        const connected = ref(!LOST_STATES.includes(connection.state ?? ''));
        let seen = connection.state === 'connected';
        const handler = ({current}: {previous: string; current: string}) => {
            if (current === 'connected') {
                seen = true;
                connected.value = true;
            } else if (current === 'connecting' || current === 'initialized') {
                connected.value = !seen;
            } else {
                connected.value = false;
            }
        };
        reconnectHandlers.push(handler);
        connection.bind('state_change', handler);

        return connected;
    }

    function privateChannel(name: string) {
        join(name);
        return echo.private(name);
    }

    function publicChannel(name: string) {
        join(name);
        return echo.channel(name);
    }

    const reconnectTimers = new Set<ReturnType<typeof setTimeout>>();

    function onReconnect(callback: () => void): void {
        let wasDisconnected = false;
        const handler = ({current}: {previous: string; current: string}) => {
            if (current !== 'connected') {
                wasDisconnected = true;
            } else if (wasDisconnected) {
                wasDisconnected = false;
                const timer = setTimeout(() => {
                    reconnectTimers.delete(timer);
                    callback();
                }, Math.random() * RECONNECT_JITTER_MS);
                reconnectTimers.add(timer);
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
        reconnectTimers.forEach((timer) => clearTimeout(timer));
        reconnectTimers.clear();
        reconnectHandlers.forEach((handler) => connection.unbind('state_change', handler));
        reconnectHandlers.length = 0;
    });

    return {privateChannel, publicChannel, onReconnect, trackConnection};
}
