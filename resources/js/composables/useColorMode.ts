import {usePage} from '@inertiajs/vue3';
import {watchEffect} from 'vue';
import type {SharedProps} from '@/types';

export function useColorMode(): void {
    const page = usePage<SharedProps>();

    watchEffect(() => {
        if (page.props.theme.darkMode) {
            document.body.setAttribute('data-bs-theme', 'dark');
        } else {
            document.body.removeAttribute('data-bs-theme');
        }
    });
}
