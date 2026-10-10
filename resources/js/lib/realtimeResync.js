export const RESYNC_EVENT = 'realtime:resync';

export function bindRealtimeResync(echo, target = window, doc = document) {
    const emit = () => target.dispatchEvent(new Event(RESYNC_EVENT));

    const connection = echo?.connector?.pusher?.connection;
    if (connection) {
        let hasConnected = false;
        connection.bind('state_change', ({current}) => {
            if (current !== 'connected') {
                return;
            }
            if (hasConnected) {
                emit();
            }
            hasConnected = true;
        });
    }

    doc.addEventListener('visibilitychange', () => {
        if (doc.visibilityState === 'visible') {
            emit();
        }
    });
}
