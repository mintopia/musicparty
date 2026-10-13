import type {RealtimeConfig} from '@/types';

export function echoOptions(config: RealtimeConfig) {
    return {
        broadcaster: 'reverb' as const,
        key: config.appKey ?? '',
        wsHost: config.host,
        wsPort: config.port,
        wssPort: config.port,
        forceTLS: config.forceTLS,
        enabledTransports: ['ws', 'wss'] as Array<'ws' | 'wss'>,
    };
}
