import {enableAutoUnmount, mount} from '@vue/test-utils';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {h, reactive} from 'vue';

const reload = vi.fn();
const pageProps = reactive({});

vi.mock('@inertiajs/vue3', () => ({
    Head: {render: () => null},
    Link: {props: ['href'], template: '<a :href="href"><slot /></a>'},
    router: {reload: (...args) => reload(...args), put: vi.fn(), delete: vi.fn()},
    usePage: () => ({props: pageProps}),
}));

vi.mock('../Layouts/AppShell.vue', () => ({
    default: {render() {
        return h('div', this.$slots.default?.({meta: {}}));
    }},
}));

import PartyLayout from '../Layouts/PartyLayout.vue';
import Log from '../pages/Party/Log.vue';

enableAutoUnmount(afterEach);

let listeners;
let echo;

const track = (title) => ({title, artists: ['Nova Kids'], album: null, artwork_url: null, duration_ms: 200000, explicit: false});
const playing = (title, over = {}) => ({id: 1, track: track(title), requested_by: {name: 'Alex'}, started_at: null, position_ms: 30000, paused: false, net_rating: 0, ...over});

const setContext = (nowPlaying, state = 'live', extra = {}) => {
    Object.assign(pageProps, {
        party: {code: 'FRI1', name: 'Friday'},
        entries: {data: [], links: {}},
        partyContext: {party: {code: 'FRI1', name: 'Friday', state}, nowPlaying, role: 'host', menu: [], themeCss: ':root{--x:1}', ...extra},
    });
};

const mountLog = () => {
    echo.channel.mockClear();

    return mount({render: () => h(PartyLayout, null, {default: () => h(Log, {party: pageProps.party, entries: pageProps.entries})})});
};

beforeEach(() => {
    vi.useFakeTimers();
    listeners = {};
    const channel = {listen: vi.fn((event, cb) => { listeners[event] = cb; return channel; })};
    echo = {channel: vi.fn(() => channel), private: vi.fn(() => channel), join: vi.fn(() => channel), leave: vi.fn()};
    window.Echo = echo;
    reload.mockReset();
    Object.keys(pageProps).forEach((key) => delete pageProps[key]);
});

afterEach(() => {
    vi.useRealTimers();
    delete window.Echo;
});

describe('PartyLayout', () => {
    it('renders the Now Playing header above the Log page with the Party theme CSS', () => {
        setContext(playing('Dance Floor Gravity'));
        const w = mountLog();

        expect(w.get('[data-testid=now-playing-title]').text()).toBe('Dance Floor Gravity');
        expect(w.get('[data-testid=party-theme-css]').text()).toBe(':root{--x:1}');
    });

    it('says nothing is playing when there is no track', () => {
        setContext(null);

        expect(mountLog().get('[data-testid=now-playing-empty]').text()).toBe('There is nothing playing');
    });

    it('updates the header from party.state_changed without a reload', async () => {
        setContext(playing('Dance Floor Gravity'));
        const w = mountLog();
        expect(echo.channel).toHaveBeenCalledWith('party.FRI1');
        expect(w.get('[data-testid=party-header-state]').text()).toBe('live');

        listeners['.party.state_changed']({state: 'paused'});
        await w.vm.$nextTick();

        expect(w.get('[data-testid=party-header-state]').text()).toBe('paused');
        expect(reload).not.toHaveBeenCalled();
    });

    it('updates the track from queue.updated and reloads on a newer payload version', async () => {
        setContext(playing('Dance Floor Gravity'));
        const w = mountLog();

        listeners['.queue.updated']({version: 1, now_playing: playing('Lights Out'), started_at: null, up_next: null, queue: []});
        await w.vm.$nextTick();
        expect(w.get('[data-testid=now-playing-title]').text()).toBe('Lights Out');

        listeners['.queue.updated']({version: 1, now_playing: null, up_next: null, queue: []});
        await w.vm.$nextTick();
        expect(w.find('[data-testid=now-playing-empty]').exists()).toBe(true);

        listeners['.queue.updated']({version: 2});
        expect(reload).toHaveBeenCalledWith(expect.objectContaining({only: ['partyContext']}));
    });

    it('ticks the progress bar each second and freezes while paused', async () => {
        setContext(playing('Dance Floor Gravity', {position_ms: 50000}));
        const w = mountLog();
        expect(w.get('[data-testid=now-playing-elapsed]').text()).toBe('0:50');

        vi.advanceTimersByTime(3000);
        await w.vm.$nextTick();
        expect(w.get('[data-testid=now-playing-elapsed]').text()).toBe('0:53');

        listeners['.party.state_changed']({state: 'paused'});
        await w.vm.$nextTick();
        vi.advanceTimersByTime(5000);
        await w.vm.$nextTick();
        expect(w.get('[data-testid=now-playing-elapsed]').text()).toBe('0:50');
    });

    it('leaves the Party channel on unmount', () => {
        setContext(null);
        mountLog().unmount();

        expect(echo.leave).toHaveBeenCalledWith('party.FRI1');
    });

    it('renders no header without a Party context', () => {
        Object.assign(pageProps, {party: {code: 'FRI1', name: 'Friday'}, entries: {data: [], links: {}}});

        expect(mountLog().find('[data-testid=party-header]').exists()).toBe(false);
    });
});
