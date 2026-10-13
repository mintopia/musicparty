import axios from 'axios';
import {ref, toValue, type MaybeRefOrGetter} from 'vue';
import {store} from '@/routes/api/v1/parties/upcomingsongs';

export type RequestStatus = 'idle' | 'pending' | 'success' | 'blocked' | 'error';

export function useRequestTrack(party: MaybeRefOrGetter<string>) {
    const status = ref<RequestStatus>('idle');
    const reason = ref<string | null>(null);

    async function request(providerTrackId: string): Promise<void> {
        status.value = 'pending';
        reason.value = null;

        const route = store(toValue(party));

        try {
            await axios.request({url: route.url, method: route.method, data: {provider_track_id: providerTrackId}});
            status.value = 'success';
        } catch (error) {
            if (!axios.isAxiosError(error) || !error.response) {
                status.value = 'error';
                reason.value = 'Could not reach the server. Please try again.';
                return;
            }

            const message = (error.response.data as {message?: string} | undefined)?.message ?? null;
            if (error.response.status === 400 || error.response.status === 422) {
                status.value = 'blocked';
                reason.value = message ?? 'This track cannot be requested.';
            } else {
                status.value = 'error';
                reason.value = message ?? 'Something went wrong. Please try again.';
            }
        }
    }

    return {status, reason, request};
}
