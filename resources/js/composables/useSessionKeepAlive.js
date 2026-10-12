import {onBeforeUnmount, onMounted} from 'vue';
import {startSessionKeepAlive} from '../lib/sessionKeepAlive';

export function useSessionKeepAlive() {
    let stop = null;

    onMounted(() => {
        stop = startSessionKeepAlive(() => {
            window.axios?.get('/session/keep-alive').catch(() => {});
        });
    });

    onBeforeUnmount(() => stop?.());
}
