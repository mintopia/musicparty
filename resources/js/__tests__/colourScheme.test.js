import {beforeEach, describe, expect, it, vi} from 'vitest';
import {effectScope} from 'vue';

const page = vi.hoisted(() => ({props: {}}));
const put = vi.hoisted(() => vi.fn());

vi.mock('@inertiajs/vue3', () => ({usePage: () => page}));
vi.mock('axios', () => ({default: {put}}));

import {applyColourScheme, resolveDark, SCHEMES, useColourScheme} from '../colourScheme';

function fakeMatchMedia(matches) {
    const listeners = new Set();
    const query = {
        matches,
        addEventListener: (_, fn) => listeners.add(fn),
        removeEventListener: (_, fn) => listeners.delete(fn),
        emit(next) {
            query.matches = next;
            listeners.forEach((fn) => fn({matches: next}));
        },
        listeners,
    };
    return query;
}

beforeEach(() => {
    document.documentElement.classList.remove('dark');
    localStorage.clear();
    page.props = {auth: {user: null}};
    put.mockReset();
});

describe('resolveDark', () => {
    it.each([
        ['light', true, false],
        ['light', false, false],
        ['dark', true, true],
        ['dark', false, true],
        ['system', true, true],
        ['system', false, false],
    ])('%s with prefersDark=%s is %s', (scheme, prefersDark, expected) => {
        expect(resolveDark(scheme, prefersDark)).toBe(expected);
    });

    it('exposes the three schemes', () => {
        expect(SCHEMES).toEqual(['light', 'dark', 'system']);
    });
});

describe('applyColourScheme', () => {
    it('toggles the dark class and persists', () => {
        const root = document.createElement('div');
        const storage = localStorage;
        const matchMedia = () => ({matches: false});

        applyColourScheme('dark', {root, matchMedia, storage});
        expect(root.classList.contains('dark')).toBe(true);
        expect(storage.getItem('colourScheme')).toBe('dark');

        applyColourScheme('light', {root, matchMedia, storage});
        expect(root.classList.contains('dark')).toBe(false);
        expect(storage.getItem('colourScheme')).toBe('light');
    });

    it('system follows matchMedia', () => {
        const root = document.createElement('div');

        applyColourScheme('system', {root, matchMedia: () => ({matches: true}), storage: localStorage});
        expect(root.classList.contains('dark')).toBe(true);

        applyColourScheme('system', {root, matchMedia: () => ({matches: false}), storage: localStorage});
        expect(root.classList.contains('dark')).toBe(false);
    });
});

describe('useColourScheme', () => {
    it('seeds from page props, then storage, then system', () => {
        window.matchMedia = () => fakeMatchMedia(false);

        page.props = {colourScheme: 'dark', auth: {user: null}};
        expect(useColourScheme().scheme.value).toBe('dark');

        page.props = {auth: {user: null}};
        localStorage.setItem('colourScheme', 'light');
        expect(useColourScheme().scheme.value).toBe('light');

        localStorage.clear();
        expect(useColourScheme().scheme.value).toBe('system');
    });

    it('reacts to OS changes only while system', () => {
        const media = fakeMatchMedia(false);
        window.matchMedia = () => media;
        const scope = effectScope();
        const {setScheme} = scope.run(() => useColourScheme());

        setScheme('system');
        media.emit(true);
        expect(document.documentElement.classList.contains('dark')).toBe(true);

        setScheme('light');
        media.emit(true);
        expect(document.documentElement.classList.contains('dark')).toBe(false);

        scope.stop();
        expect(media.listeners.size).toBe(0);
    });

    it('keeps the local choice when the request fails', async () => {
        window.matchMedia = () => fakeMatchMedia(false);
        page.props = {auth: {user: {id: 1}}};
        put.mockRejectedValue(new Error('boom'));
        const {scheme, setScheme} = useColourScheme();

        await setScheme('dark');

        expect(put).toHaveBeenCalledWith('/colour-scheme', {colour_scheme: 'dark'});
        expect(scheme.value).toBe('dark');
        expect(document.documentElement.classList.contains('dark')).toBe(true);
    });
});
