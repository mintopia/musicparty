import {router} from '@inertiajs/vue3';
import {onBeforeUnmount} from 'vue';
import {useEcho} from '@/composables/useEcho';

type Subscription = {
    listen(event: string, cb: () => void): unknown;
    stopListening(event: string, cb?: () => void): unknown;
};

const EVENTS = ['UpcomingSong\\UpdatedEvent', 'UpcomingSong\\RemovedEvent'];

export function useQueueSync(party: string, only: string[]): void {
    let timer: ReturnType<typeof setTimeout> | null = null;

    const refresh = (): void => {
        if (timer !== null) {
            return;
        }
        timer = setTimeout(() => {
            timer = null;
            router.reload({only});
        }, 250);
    };

    const channel = useEcho().privateChannel(`party.${party}`) as Subscription;
    EVENTS.forEach((event) => channel.listen(event, refresh));

    onBeforeUnmount(() => {
        EVENTS.forEach((event) => channel.stopListening(event, refresh));
        if (timer !== null) {
            clearTimeout(timer);
            timer = null;
        }
    });
}
