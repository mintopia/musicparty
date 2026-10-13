import {usePage} from '@inertiajs/vue3';
import {watchEffect} from 'vue';
import {applyTheme} from '@/lib/theme';
import type {SharedProps} from '@/types';

export function useColorMode(): void {
    const page = usePage<SharedProps>();

    watchEffect(() => applyTheme(page.props.theme));
}
