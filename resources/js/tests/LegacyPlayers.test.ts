import {mount} from '@vue/test-utils';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import Next from '../components/Next.vue';
import TvPlayer from '../components/TvPlayer.vue';
import Upcoming from '../components/Upcoming.vue';
import {legacyAccentStyle} from '../lib/decoration';

vi.mock('@ts-pro/vue-eternal-loading', () => ({VueEternalLoading: {template: '<div />'}}));

const decoration = {label: 'Boosted', accent: '#ff0000', class: 'mod-row', displayName: 'Mod Track'};

const track = (extra: Record<string, unknown> = {}) => ({
    provider_track_id: 't1',
    name: 'Song',
    artists: [{name: 'A'}],
    album: {name: 'Al', image_url: ''},
    length: 200000,
    score: 1,
    user: null,
    css_classes: '',
    fallback_name: 'Fallback Track',
    ...extra,
});

const echoStub = () => {
    (window as unknown as {Echo: unknown}).Echo = {
        channel: () => ({listen: () => {}}),
        private: () => ({listen: () => {}}),
    };
};

describe('legacyAccentStyle', () => {
    it('returns a left border only when an accent is set', () => {
        expect(legacyAccentStyle(track({decoration}) as never)).toEqual({borderLeft: '4px solid #ff0000'});
        expect(legacyAccentStyle(track() as never)).toBeUndefined();
    });
});

describe('legacy decoration rendering', () => {
    beforeEach(echoStub);

    it('renders label and accent in the queue view', async () => {
        const wrapper = mount(Upcoming, {
            props: {
                party: 'ABC',
                timezone: 'UTC',
                initialstate: JSON.stringify([
                    {...track({decoration}), id: 1, vote: 0, created_at: '2030-01-01T00:00:00Z'},
                ]),
            },
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.find('[data-test="decoration-label"]').text()).toBe('Boosted');
        expect(wrapper.find('.row').attributes('style')).toContain('border-left');
    });

    it('renders label and accent in the up next view', async () => {
        const wrapper = mount(Next, {
            props: {code: 'ABC', initialstate: JSON.stringify({next: track({decoration})})},
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.find('[data-test="decoration-label"]').text()).toBe('Boosted');
        expect(wrapper.find('.row').attributes('style')).toContain('border-left');
    });

    it('renders label, accent and display name for the next track on the TV view', async () => {
        const wrapper = mount(TvPlayer, {
            props: {
                code: 'ABC',
                partyurl: 'x',
                initialstate: JSON.stringify({
                    name: 'P',
                    now: track(),
                    next: track({decoration}),
                    current: {id: 1, provider_track_id: 't1', user: null, rating: 0},
                    status: null,
                    show_qrcode: false,
                }),
            },
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.find('[data-test="decoration-label"]').text()).toBe('Boosted');
        expect(wrapper.html()).toContain('border-left');
        expect(wrapper.text()).toContain('Mod Track');
    });
});

describe('TvPlayer progress', () => {
    beforeEach(() => {
        echoStub();
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2030-01-01T00:01:00Z'));
    });
    afterEach(() => vi.useRealTimers());

    it('interpolates from sampledAt and progressMs', async () => {
        const wrapper = mount(TvPlayer, {
            props: {
                code: 'ABC',
                partyurl: 'x',
                initialstate: JSON.stringify({
                    name: 'P',
                    now: track(),
                    next: null,
                    current: {id: 1, provider_track_id: 't1', user: null, rating: 0},
                    status: {
                        isPlaying: true,
                        sampledAt: '2030-01-01T00:00:30Z',
                        progressMs: 10000,
                        length: 200000,
                    },
                    show_qrcode: false,
                }),
            },
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('0:40');
    });
});

describe('legacy player channel subscriptions', () => {
    afterEach(() => {
        delete (window as unknown as {Echo?: unknown}).Echo;
    });

    it('subscribes the queue to the private party.{code} channel', async () => {
        const privateChannel = vi.fn(() => ({listen: () => {}}));
        (window as unknown as {Echo: unknown}).Echo = {channel: vi.fn(), private: privateChannel};

        mount(Upcoming, {props: {party: 'ABC', timezone: 'UTC', initialstate: JSON.stringify([])}});

        expect(privateChannel).toHaveBeenCalledWith('party.ABC');
    });

    it('subscribes up next to party.{code} and updates on Party\\UpdatedEvent', async () => {
        let handler: ((payload: unknown) => void) | undefined;
        const channel = vi.fn(() => ({
            listen: (event: string, cb: (payload: unknown) => void) => {
                expect(event).toBe('Party\\UpdatedEvent');
                handler = cb;
            },
        }));
        (window as unknown as {Echo: unknown}).Echo = {channel, private: vi.fn()};

        const wrapper = mount(Next, {props: {code: 'ABC', initialstate: JSON.stringify({next: track()})}});
        expect(channel).toHaveBeenCalledWith('party.ABC');

        handler?.({next: track({name: 'Pushed Song'})});
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('Pushed Song');
    });

    it('subscribes the TV player to party.{code}', () => {
        const channel = vi.fn(() => ({listen: () => {}}));
        (window as unknown as {Echo: unknown}).Echo = {channel, private: vi.fn()};

        mount(TvPlayer, {
            props: {
                code: 'ABC',
                partyurl: 'x',
                initialstate: JSON.stringify({name: 'P', now: track(), next: track(), current: {id: 1, provider_track_id: 't1', user: null, rating: 0}, status: null}),
            },
        });

        expect(channel).toHaveBeenCalledWith('party.ABC');
    });
});
