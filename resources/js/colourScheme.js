import axios from 'axios';
import {usePage} from '@inertiajs/vue3';
import {getCurrentScope, onScopeDispose, ref} from 'vue';

export const SCHEMES = ['light', 'dark', 'system'];

const STORAGE_KEY = 'colourScheme';

export function resolveDark(scheme, prefersDark) {
    if (scheme === 'dark') {
        return true;
    }

    if (scheme === 'light') {
        return false;
    }

    return Boolean(prefersDark);
}

export function applyColourScheme(
    scheme,
    {
        root = document.documentElement,
        matchMedia = window.matchMedia.bind(window),
        storage = window.localStorage,
    } = {},
) {
    const prefersDark = matchMedia('(prefers-color-scheme: dark)').matches;
    root.classList.toggle('dark', resolveDark(scheme, prefersDark));

    try {
        storage.setItem(STORAGE_KEY, scheme);
    } catch {
        return;
    }
}

function readStoredScheme() {
    try {
        return window.localStorage.getItem(STORAGE_KEY);
    } catch {
        return null;
    }
}

export function useColourScheme() {
    const page = usePage();
    const initial = [page.props.colourScheme, readStoredScheme()].find((scheme) =>
        SCHEMES.includes(scheme),
    );
    const scheme = ref(initial ?? 'system');

    const setScheme = async (next) => {
        if (!SCHEMES.includes(next)) {
            return;
        }

        scheme.value = next;
        applyColourScheme(next);

        if (!page.props.auth?.user) {
            return;
        }

        try {
            await axios.put('/colour-scheme', {colour_scheme: next});
        } catch {
            return;
        }
    };

    const query = window.matchMedia('(prefers-color-scheme: dark)');
    const onSystemChange = () => {
        if (scheme.value === 'system') {
            applyColourScheme('system');
        }
    };
    query.addEventListener?.('change', onSystemChange);

    if (getCurrentScope()) {
        onScopeDispose(() => query.removeEventListener?.('change', onSystemChange));
    }

    return {scheme, setScheme};
}
