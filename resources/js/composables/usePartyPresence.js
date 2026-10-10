import {onBeforeUnmount, onMounted} from 'vue';

export function usePartyPresence(partyCode) {
    const channelName = `party.${partyCode}.members`;

    onMounted(() => {
        window.Echo?.join(channelName);
    });

    onBeforeUnmount(() => {
        window.Echo?.leave(channelName);
    });
}
