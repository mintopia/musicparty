import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {useQueueSync} from '@/composables/useQueueSync';

const inertia = vi.hoisted(() => ({reload: vi.fn()}));
const handlers = vi.hoisted(() => ({
    map: {} as Record<string, () => void>,
    channel: '',
    stopped: [] as string[],
    unmount: null as null | (() => void),
}));

vi.mock('@inertiajs/vue3', () => ({router: inertia}));
vi.mock('vue', async (original) => ({
    ...(await original<typeof import('vue')>()),
    onBeforeUnmount: (cb: () => void) => {
        handlers.unmount = cb;
    },
}));
vi.mock('@/composables/useEcho', () => ({
    useEcho: () => ({
        privateChannel: (name: string) => {
            handlers.channel = name;
            return {
                listen: (event: string, cb: () => void) => {
                    handlers.map[event] = cb;
                },
                stopListening: (event: string) => {
                    handlers.stopped.push(event);
                },
            };
        },
    }),
}));

describe('useQueueSync', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        inertia.reload.mockClear();
    });
    afterEach(() => vi.useRealTimers());

    it('reloads the given props once after a burst of queue events', () => {
        useQueueSync('ABC', ['tracks']);

        expect(handlers.channel).toBe('party.ABC');
        handlers.map['UpcomingSong\\UpdatedEvent']();
        handlers.map['UpcomingSong\\RemovedEvent']();
        vi.advanceTimersByTime(300);

        expect(inertia.reload).toHaveBeenCalledTimes(1);
        expect(inertia.reload).toHaveBeenCalledWith({only: ['tracks']});
    });

    it('stops listening and cancels a pending reload on unmount', () => {
        useQueueSync('ABC', ['tracks']);
        handlers.map['UpcomingSong\\UpdatedEvent']();
        handlers.unmount?.();
        vi.advanceTimersByTime(300);

        expect(handlers.stopped).toEqual(['UpcomingSong\\UpdatedEvent', 'UpcomingSong\\RemovedEvent']);
        expect(inertia.reload).not.toHaveBeenCalled();
    });
});
