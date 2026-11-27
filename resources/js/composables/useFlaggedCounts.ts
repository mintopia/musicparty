import {usePage} from '@inertiajs/vue3';
import {onBeforeUnmount, ref, watch} from 'vue';
import {useEcho} from '@/composables/useEcho';
import type {SharedProps} from '@/types';

type CountPayload = {flaggedCount?: number};
type ManagersChannel = {
    listen(event: string, cb: (payload: CountPayload) => void): unknown;
    stopListening?(event: string, cb?: (payload: CountPayload) => void): unknown;
};

const counts = ref<Record<string, number>>({});

export function setFlaggedCount(code: string, count: number): void {
    counts.value = {...counts.value, [code]: count};
}

export function flaggedCountFor(code: string, fallback = 0): number {
    return counts.value[code] ?? fallback;
}

export function resetFlaggedCounts(): void {
    counts.value = {};
}

export function useFlaggedCountSync(): void {
    const page = usePage<SharedProps>();
    const events = ['FlaggedRequestAdded', 'FlaggedRequestResolved'];
    const subscriptions: Array<{channel: ManagersChannel; handler: (payload: CountPayload) => void}> = [];

    const managed = () => (page.props.auth?.parties ?? []).filter((party) => party.canManage || party.canModerate);

    watch(
        () => page.props.auth?.parties,
        () => managed().forEach((party) => setFlaggedCount(party.code, party.flaggedCount ?? 0)),
        {immediate: true},
    );

    if (!page.props.reverb) {
        return;
    }

    const {privateChannel} = useEcho();
    managed().forEach((party) => {
        const channel = privateChannel(`party.${party.code}.managers`) as ManagersChannel;
        const handler = (payload: CountPayload) => {
            if (typeof payload.flaggedCount === 'number') {
                setFlaggedCount(party.code, payload.flaggedCount);
            }
        };
        events.forEach((event) => channel.listen(event, handler));
        subscriptions.push({channel, handler});
    });

    onBeforeUnmount(() => {
        subscriptions.forEach(({channel, handler}) => {
            events.forEach((event) => channel.stopListening?.(event, handler));
        });
    });
}
