import type {AxiosStatic} from 'axios';
import type Echo from 'laravel-echo';
import type {LoDashStatic} from 'lodash';
import type Pusher from 'pusher-js';

declare global {
    interface Window {
        _: LoDashStatic;
        Popper: typeof import('@popperjs/core');
        axios: AxiosStatic;
        Echo: Echo<'reverb'>;
        Pusher: typeof Pusher;
        pusherConfig: {appKey: string | null};
    }
}

export {};
