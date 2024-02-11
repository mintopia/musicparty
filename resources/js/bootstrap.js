import loadash from 'lodash'
window._ = loadash

import * as Popper from '@popperjs/core'
window.Popper = Popper

/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allows your team to easily build robust real-time web applications.
 */

import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

window.Echo = new Echo({
     broadcaster: 'pusher',
     key: window.pusherConfig.appKey ?? import.meta.env.VITE_PUSHER_APP_KEY,
     cluster: window.pusherConfig.cluster ?? import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'mt1',
     wsHost: window.pusherConfig.host ?? import.meta.env.VITE_PUSHER_HOST ? import.meta.env.VITE_PUSHER_HOST : `ws-${import.meta.env.VITE_PUSHER_APP_CLUSTER}.pusher.com`,
     wsPort: window.pusherConfig.port ?? import.meta.env.VITE_PUSHER_PORT ?? 80,
     wssPort: window.pusherConfig.port ?? import.meta.env.VITE_PUSHER_PORT ?? 443,
     forceTLS: (window.pusherConfig.scheme ?? import.meta.env.VITE_PUSHER_SCHEME ?? 'https') === 'https',
     enabledTransports: ['ws', 'wss'],
});
