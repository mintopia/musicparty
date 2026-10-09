import {ref} from 'vue';
import {router} from '@inertiajs/vue3';

export const useRatePlay = (getPartyCode, isLocked) => {
    const pending = ref(false);
    const error = ref(null);

    const rate = (play, direction) => {
        if (isLocked() || pending.value || !play) {
            return;
        }
        const value = direction === 'up' ? 1 : -1;
        const url = `/parties/${getPartyCode()}/plays/${play.id}/rating`;
        const options = {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                pending.value = true;
                error.value = null;
            },
            onError: (e) => {
                error.value = e.rating ?? e.value ?? 'Could not record your rating.';
            },
            onFinish: () => {
                pending.value = false;
            },
        };

        if (play.my_rating === value) {
            router.delete(url, options);
        } else {
            router.put(url, {value: direction}, options);
        }
    };

    return {pending, error, rate};
};
