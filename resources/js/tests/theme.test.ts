import {afterEach, describe, expect, it} from 'vitest';
import {applyTheme} from '@/lib/theme';

const root = document.documentElement;

afterEach(() => {
    applyTheme({tokens: {}, stylesheet: null, darkMode: false});
    document.body.removeAttribute('data-bs-theme');
});

describe('applyTheme', () => {
    it('sets token variables with the derived primary rgb and link overrides', () => {
        applyTheme({tokens: {primary: '#ff8000', surface: '#fff'}, stylesheet: null, darkMode: false});

        expect(root.style.getPropertyValue('--mp-primary')).toBe('#ff8000');
        expect(root.style.getPropertyValue('--mp-surface')).toBe('#fff');
        expect(root.style.getPropertyValue('--mp-primary-rgb')).toBe('255, 128, 0');
        expect(root.style.getPropertyValue('--tblr-link-color')).toBe('var(--mp-text-primary)');
        expect(root.style.getPropertyValue('--tblr-link-hover-color-rgb')).toBe('var(--mp-primary-rgb)');
    });

    it('expands short hex and removes variables dropped by a later theme', () => {
        applyTheme({tokens: {primary: '#f80'}, stylesheet: null, darkMode: false});
        expect(root.style.getPropertyValue('--mp-primary-rgb')).toBe('255, 136, 0');

        applyTheme({tokens: {surface: '#000'}, stylesheet: null, darkMode: false});
        expect(root.style.getPropertyValue('--mp-primary')).toBe('');
        expect(root.style.getPropertyValue('--mp-primary-rgb')).toBe('');
        expect(root.style.getPropertyValue('--tblr-link-color')).toBe('');
    });

    it('keeps a single stylesheet link, updates it, and removes it', () => {
        applyTheme({tokens: {}, stylesheet: '/theme.css?v=1', darkMode: false});
        applyTheme({tokens: {}, stylesheet: '/theme.css?v=2', darkMode: false});

        const links = document.querySelectorAll('link#mp-theme-stylesheet');
        expect(links).toHaveLength(1);
        expect(links[0].getAttribute('href')).toBe('/theme.css?v=2');

        applyTheme({tokens: {}, stylesheet: null, darkMode: false});
        expect(document.querySelector('link#mp-theme-stylesheet')).toBeNull();
    });

    it('sets link overrides on the body so they outrank the dark scope', () => {
        applyTheme({tokens: {primary: '#a86ef0'}, stylesheet: null, darkMode: true});
        expect(document.body.style.getPropertyValue('--tblr-link-color')).toBe('var(--mp-text-primary)');

        applyTheme({tokens: {}, stylesheet: null, darkMode: true});
        expect(document.body.style.getPropertyValue('--tblr-link-color')).toBe('');
    });

    it('toggles dark mode on the body', () => {
        applyTheme({tokens: {}, stylesheet: null, darkMode: true});
        expect(document.body.getAttribute('data-bs-theme')).toBe('dark');

        applyTheme({tokens: {}, stylesheet: null, darkMode: false});
        expect(document.body.hasAttribute('data-bs-theme')).toBe(false);
    });
});
