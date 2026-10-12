export const KEEP_ALIVE_INTERVAL_MS = 60000;

export function startSessionKeepAlive(ping, doc = document, intervalMs = KEEP_ALIVE_INTERVAL_MS) {
    let timer = null;

    const start = () => {
        if (timer === null) {
            timer = setInterval(ping, intervalMs);
        }
    };

    const stop = () => {
        if (timer !== null) {
            clearInterval(timer);
            timer = null;
        }
    };

    const onVisibilityChange = () => {
        if (doc.visibilityState === 'hidden') {
            stop();
        } else {
            start();
        }
    };

    doc.addEventListener('visibilitychange', onVisibilityChange);
    if (doc.visibilityState !== 'hidden') {
        start();
    }

    return () => {
        stop();
        doc.removeEventListener('visibilitychange', onVisibilityChange);
    };
}
