import {onBeforeUnmount, onMounted, ref} from 'vue';
import type {Ref} from 'vue';

const PHONE_QUERY = '(max-width: 767.98px)';

export function useIsPhone(): Ref<boolean> {
    const isPhone = ref(false);
    let media: MediaQueryList | null = null;
    const sync = (): void => {
        isPhone.value = media?.matches ?? false;
    };

    onMounted(() => {
        media = typeof window.matchMedia === 'function' ? window.matchMedia(PHONE_QUERY) : null;
        sync();
        media?.addEventListener('change', sync);
    });
    onBeforeUnmount(() => media?.removeEventListener('change', sync));

    return isPhone;
}
