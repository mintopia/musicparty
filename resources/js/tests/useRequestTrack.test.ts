import axios, {AxiosError, type InternalAxiosRequestConfig} from 'axios';
import {afterEach, describe, expect, it} from 'vitest';
import {useRequestTrack} from '@/composables/useRequestTrack';

const originalAdapter = axios.defaults.adapter;
const sent: Array<{url?: string; method?: string; data?: string}> = [];

function respondWith(handler: (config: InternalAxiosRequestConfig) => unknown): void {
    axios.defaults.adapter = (config) => {
        sent.push({url: config.url, method: config.method, data: config.data});
        return new Promise((resolve, reject) => {
            try {
                resolve(handler(config) as never);
            } catch (error) {
                reject(error);
            }
        });
    };
}

function httpError(status: number, message: string, config: InternalAxiosRequestConfig): AxiosError {
    return new AxiosError('failed', String(status), config, undefined, {
        status,
        data: {message},
    } as never);
}

describe('useRequestTrack', () => {
    afterEach(() => {
        axios.defaults.adapter = originalAdapter;
        sent.length = 0;
    });

    it('succeeds and posts the provider track id', async () => {
        respondWith((config) => ({data: {}, status: 201, statusText: 'Created', headers: {}, config}));
        const {status, reason, request: run} = useRequestTrack('ABC');

        await run('spotify:1');

        expect(status.value).toBe('success');
        expect(reason.value).toBeNull();
        expect(sent).toEqual([
            {url: '/api/v1/parties/ABC/upcomingsongs', method: 'post', data: '{"provider_track_id":"spotify:1"}'},
        ]);
    });

    it('posts scheduled_for only when a time is given', async () => {
        respondWith((config) => ({data: {}, status: 201, statusText: 'Created', headers: {}, config}));
        const {request: run} = useRequestTrack('ABC');

        await run('spotify:1', '2030-01-01T20:00');
        await run('spotify:2', '');

        expect(sent.map((call) => call.data)).toEqual([
            '{"provider_track_id":"spotify:1","scheduled_for":"2030-01-01T20:00"}',
            '{"provider_track_id":"spotify:2"}',
        ]);
    });

    it('reports blocked requests with the server reason', async () => {
        respondWith((config) => {
            throw httpError(400, 'Already queued', config);
        });
        const {status, reason, request: run} = useRequestTrack('ABC');

        await run('spotify:1');

        expect(status.value).toBe('blocked');
        expect(reason.value).toBe('Already queued');
    });

    it('reports a generic error on network failure', async () => {
        respondWith(() => {
            throw new AxiosError('Network Error');
        });
        const {status, reason, request: run} = useRequestTrack('ABC');

        await run('spotify:1');

        expect(status.value).toBe('error');
        expect(reason.value).toBeTruthy();
    });
});
