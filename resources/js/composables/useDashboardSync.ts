import {router} from '@inertiajs/vue3';
import {onBeforeUnmount} from 'vue';
import {useEcho} from '@/composables/useEcho';
import type {LegacyPartyState} from '@/types';

type Subscription = {
    listen<T>(event: string, cb: (payload: T) => void): unknown;
    stopListening(event: string, cb?: (payload: never) => void): unknown;
};

const QUEUE = ['upNext', 'upcoming'];
const CONNECTION_REFRESH_MS = 15000;
const ALL = ['playerState', 'control', 'upNext', 'upcoming', 'attention'];

export function useDashboardSync(code: string, onState: (state: LegacyPartyState) => void): void {
    const pending = new Set<string>();
    let timer: ReturnType<typeof setTimeout> | null = null;

    const schedule = (keys: string[]): void => {
        keys.forEach((key) => pending.add(key));
        if (timer !== null) {
            return;
        }
        timer = setTimeout(() => {
            timer = null;
            const only = [...pending];
            pending.clear();
            router.reload({only});
        }, 250);
    };

    const onParty = (payload: LegacyPartyState): void => {
        onState(payload);
        schedule(['control', 'attention', ...QUEUE]);
    };
    const onQueue = (): void => schedule(QUEUE);
    const onManagers = (): void => schedule(['attention', ...QUEUE]);

    const echo = useEcho();
    const party = echo.publicChannel(`party.${code}`) as Subscription;
    const queue = echo.privateChannel(`party.${code}`) as Subscription;
    const managers = echo.privateChannel(`party.${code}.managers`) as Subscription;

    party.listen('Party\\UpdatedEvent', onParty);
    queue.listen('UpcomingSong\\UpdatedEvent', onQueue);
    queue.listen('UpcomingSong\\RemovedEvent', onQueue);
    managers.listen('FlaggedRequestAdded', onManagers);
    managers.listen('FlaggedRequestResolved', onManagers);
    const connectionTimer = setInterval(() => router.reload({only: ['control', 'attention']}), CONNECTION_REFRESH_MS);
    echo.onReconnect(() => router.reload({only: ALL}));

    onBeforeUnmount(() => {
        clearInterval(connectionTimer);
        party.stopListening('Party\\UpdatedEvent', onParty);
        queue.stopListening('UpcomingSong\\UpdatedEvent', onQueue);
        queue.stopListening('UpcomingSong\\RemovedEvent', onQueue);
        managers.stopListening('FlaggedRequestAdded', onManagers);
        managers.stopListening('FlaggedRequestResolved', onManagers);
        if (timer !== null) {
            clearTimeout(timer);
            timer = null;
        }
    });
}
