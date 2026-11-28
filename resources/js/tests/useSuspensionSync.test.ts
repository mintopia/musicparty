import {beforeEach, describe, expect, it, vi} from 'vitest';
import {useSuspensionSync} from '@/composables/useSuspensionSync';

const state = vi.hoisted(() => ({
    props: {} as Record<string, unknown>,
    channels: [] as string[],
    handlers: {} as Record<string, () => void>,
}));

vi.mock('@inertiajs/vue3', () => ({usePage: () => ({props: state.props})}));
vi.mock('@/composables/useEcho', () => ({
    useEcho: () => ({
        privateChannel: (name: string) => {
            state.channels.push(name);
            const channel = {
                listen: (event: string, cb: () => void) => {
                    state.handlers[event] = cb;
                    return channel;
                },
            };
            return channel;
        },
    }),
}));

describe('useSuspensionSync', () => {
    beforeEach(() => {
        state.channels = [];
        state.handlers = {};
    });

    it('reloads the page when the signed-in user is suspended', () => {
        state.props = {auth: {user: {id: 7}}, reverb: {key: 'k'}};
        const reload = vi.fn();
        vi.stubGlobal('location', {reload});

        useSuspensionSync();
        state.handlers['UserSuspended']();

        expect(state.channels).toEqual(['user.7']);
        expect(reload).toHaveBeenCalledOnce();
        vi.unstubAllGlobals();
    });

    it('stays quiet for guests', () => {
        state.props = {auth: {user: null}, reverb: {key: 'k'}};

        useSuspensionSync();

        expect(state.channels).toEqual([]);
    });
});
