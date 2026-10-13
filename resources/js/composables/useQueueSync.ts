import {router} from '@inertiajs/vue3';
import {onBeforeUnmount} from 'vue';
import {useEcho} from '@/composables/useEcho';

type Listenable = {listen(event: string, cb: () => void): unknown};

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

    const channel = useEcho().privateChannel(`party.${party}`) as Listenable;
    channel.listen('UpcomingSong\\UpdatedEvent', refresh);
    channel.listen('UpcomingSong\\RemovedEvent', refresh);

    onBeforeUnmount(() => {
        if (timer !== null) {
            clearTimeout(timer);
            timer = null;
        }
    });
}
