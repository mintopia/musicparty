import loadash from 'lodash'
import * as Popper from '@popperjs/core'
import axios from 'axios';
import Echo from 'laravel-echo';

import Pusher from 'pusher-js';

window._ = loadash

window.Popper = Popper

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

window.Echo = new Echo({
     broadcaster: 'reverb',
     key: window.pusherConfig.appKey,
     wsHost: window.pusherConfig.host,
     wsPort: window.pusherConfig.port,
     wssHost: window.pusherConfig.host,
     wssPort: window.pusherConfig.port,
     forceTLS: window.pusherConfig.scheme === 'https',
     enabledTransports: ['ws', 'wss'],
});
