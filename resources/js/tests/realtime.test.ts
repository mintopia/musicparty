import {expect, it} from 'vitest';
import {echoOptions} from '@/lib/realtime';

it('maps the shared config onto Echo options', () => {
    expect(echoOptions({appKey: 'k', host: 'mp.test', port: 8443, forceTLS: true})).toMatchObject({
        broadcaster: 'reverb',
        key: 'k',
        wsHost: 'mp.test',
        wsPort: 8443,
        wssPort: 8443,
        forceTLS: true,
    });
    expect(echoOptions({appKey: null, host: 'h', port: 80, forceTLS: false}).key).toBe('');
});
