import type {AxiosStatic} from 'axios';
import type Echo from 'laravel-echo';
import type {LoDashStatic} from 'lodash';
import type Pusher from 'pusher-js';
import type {RealtimeConfig} from '@/types';

declare global {
    interface Window {
        _: LoDashStatic;
        axios: AxiosStatic;
        Echo: Echo<'reverb'>;
        Pusher: typeof Pusher;
        pusherConfig: RealtimeConfig;
    }
}

export {};
