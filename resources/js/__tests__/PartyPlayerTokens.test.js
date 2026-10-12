import {beforeEach, describe, expect, it, vi} from 'vitest';
import {mount, flushPromises} from '@vue/test-utils';
import {reactive} from 'vue';

const handlers = vi.hoisted(() => ({navigate: []}));
const router = vi.hoisted(() => ({
    delete: vi.fn(),
    on: vi.fn((event, cb) => {
        handlers[event].push(cb);
        return () => { handlers[event] = handlers[event].filter((h) => h !== cb); };
    }),
}));
const posted = vi.hoisted(() => ({calls: []}));

vi.mock('@inertiajs/vue3', () => ({
    router,
    Head: {render: () => null},
    useForm: (data) => reactive({
        ...data,
        errors: {},
        processing: false,
        reset() { this.name = ''; },
        post(url, options) { posted.calls.push({url, options}); },
    }),
}));

import PlayerTokens from '../pages/Party/PlayerTokens.vue';

const props = (extra = {}) => ({
    party: {code: 'FRI1', name: 'Friday'},
    tokens: [{id: 7, name: 'Stage', last_used_at: null, expires_at: '2026-12-01T00:00:00+00:00'}],
    issuedToken: null,
    ...extra,
});

beforeEach(() => {
    posted.calls = [];
    handlers.navigate = [];
    router.delete.mockReset();
    vi.restoreAllMocks();
});

describe('Player Tokens page', () => {
    it('lists label, last used and expiry', () => {
        const w = mount(PlayerTokens, {props: props()});
        const row = w.get('[data-token-row]').text();
        expect(row).toContain('Stage');
        expect(row).toContain('Never');
    });

    it('shows the issued token with copy and clears it on navigation', async () => {
        const writeText = vi.fn().mockResolvedValue();
        Object.assign(navigator, {clipboard: {writeText}});
        const w = mount(PlayerTokens, {props: props({issuedToken: {name: 'Stage', token: '1|secret'}})});

        expect(w.get('[data-token]').text()).toBe('1|secret');
        await w.get('[data-action="copy"]').trigger('click');
        expect(writeText).toHaveBeenCalledWith('1|secret');

        handlers.navigate.forEach((cb) => cb());
        await flushPromises();

        expect(w.find('[data-testid="issued-token"]').exists()).toBe(false);
        expect(w.html()).not.toContain('1|secret');
    });

    it('clears the token when the page unmounts', () => {
        const w = mount(PlayerTokens, {props: props({issuedToken: {name: 'Stage', token: '1|secret'}})});
        w.unmount();
        expect(handlers.navigate).toHaveLength(0);
    });

    it('revokes only after confirmation', async () => {
        const w = mount(PlayerTokens, {props: props()});
        vi.spyOn(window, 'confirm').mockReturnValueOnce(false).mockReturnValueOnce(true);

        await w.get('[data-action="revoke"]').trigger('click');
        expect(router.delete).not.toHaveBeenCalled();

        await w.get('[data-action="revoke"]').trigger('click');
        expect(router.delete).toHaveBeenCalledWith('/parties/FRI1/player-tokens/7', {preserveScroll: true});
    });

    it('posts the label to issue a token', async () => {
        const w = mount(PlayerTokens, {props: props()});
        await w.get('#token-name').setValue('Bar');
        await w.get('form').trigger('submit');
        expect(posted.calls[0].url).toBe('/parties/FRI1/player-tokens');
    });
});
