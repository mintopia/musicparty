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
     broadcaster: 'reverb',
     key: window.pusherConfig.appKey,
     wsHost: window.pusherConfig.host,
     wsPort: window.pusherConfig.port,
     wssHost: window.pusherConfig.host,
     wssPort: window.pusherConfig.port,
     forceTLS: window.pusherConfig.scheme === 'https',
     enabledTransports: ['ws', 'wss'],
});

window.showToast = function(message, css = 'bg-primary text-white') {

    const div = document.createElement('div');
    div.innerHTML = `
         <div class="toast-container position-absolute p-3 top-0 start-50 translate-middle-x">
            <div className="toast align-items-center border-0 ${css}" role="alert" aria-live="assertive" aria-atomic="true">
                <div className="d-flex">
                    <div className="toast-body" id="toast-message">
                        ${message}
                    </div>
                    <button type="button" className="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        </div>
    `;
    const element = div.firstElementChild;
    console.log(element);

    element.addEventListener('hidden.bs.toast', function () {
        element.remove();
        div.remove();
    });

    // Append the toast and show it
    document.querySelector('body').appendChild(element);
    const toast = new bootstrap.Toast(element);
    toast.show();
}
