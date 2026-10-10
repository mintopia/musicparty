import axios from 'axios';
import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
import {bindRealtimeResync} from './lib/realtimeResync';

/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allows your team to easily build robust real-time web applications.
 */
window.Pusher = Pusher;

const config = window.pusherConfig ?? {};

const noopChannel = {};
['listen', 'listenForWhisper', 'here', 'joining', 'leaving', 'error', 'whisper'].forEach((method) => {
    noopChannel[method] = () => noopChannel;
});

window.Echo = config.appKey
    ? new Echo({
        broadcaster: 'reverb',
        key: config.appKey,
        wsHost: config.host,
        wsPort: config.port,
        wssHost: config.host,
        wssPort: config.port,
        forceTLS: config.scheme === 'https',
        enabledTransports: ['ws', 'wss'],
    })
    : {
        channel: () => noopChannel,
        private: () => noopChannel,
        join: () => noopChannel,
        leave: () => {},
    };

bindRealtimeResync(window.Echo);
