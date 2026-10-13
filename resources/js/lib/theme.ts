import type {SharedProps} from '@/types';

export type ThemeProps = SharedProps['theme'];

const STYLESHEET_ID = 'mp-theme-stylesheet';
const DERIVED = [
    '--mp-primary-rgb',
    '--tblr-link-color',
    '--tblr-link-color-rgb',
    '--tblr-link-hover-color',
    '--tblr-link-hover-color-rgb',
];

function rgb(colour: string): string {
    let hex = colour.replace('#', '');
    if (hex.length <= 4) {
        hex = [...hex].map((c) => c + c).join('');
    }
    return [0, 2, 4].map((i) => parseInt(hex.slice(i, i + 2), 16)).join(', ');
}

function applyVariables(tokens: Record<string, string>): void {
    const style = document.documentElement.style;
    for (const name of [...style].filter((n) => n.startsWith('--mp-') || DERIVED.includes(n))) {
        style.removeProperty(name);
    }
    for (const [token, value] of Object.entries(tokens)) {
        style.setProperty(`--mp-${token}`, value);
    }
    if (tokens.primary) {
        style.setProperty('--mp-primary-rgb', rgb(tokens.primary));
        style.setProperty('--tblr-link-color', 'var(--mp-primary)');
        style.setProperty('--tblr-link-color-rgb', 'var(--mp-primary-rgb)');
        style.setProperty('--tblr-link-hover-color', 'var(--mp-primary)');
        style.setProperty('--tblr-link-hover-color-rgb', 'var(--mp-primary-rgb)');
    }
}

function applyStylesheet(href: string | null): void {
    let link = document.getElementById(STYLESHEET_ID) as HTMLLinkElement | null;
    if (href === null) {
        link?.remove();
        return;
    }
    if (link === null) {
        link = document.createElement('link');
        link.id = STYLESHEET_ID;
        link.rel = 'stylesheet';
        document.head.appendChild(link);
    }
    if (link.getAttribute('href') !== href) {
        link.setAttribute('href', href);
    }
}

export function applyTheme(theme: ThemeProps): void {
    applyVariables(theme.tokens ?? {});
    applyStylesheet(theme.stylesheet ?? null);
    if (theme.darkMode) {
        document.body.setAttribute('data-bs-theme', 'dark');
    } else {
        document.body.removeAttribute('data-bs-theme');
    }
}
