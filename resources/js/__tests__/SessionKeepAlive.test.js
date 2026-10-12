import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {KEEP_ALIVE_INTERVAL_MS, startSessionKeepAlive} from '../lib/sessionKeepAlive';

let doc;
let ping;

beforeEach(() => {
    vi.useFakeTimers();
    ping = vi.fn();
    doc = new EventTarget();
    doc.visibilityState = 'visible';
});

afterEach(() => {
    vi.useRealTimers();
});

const setVisibility = (state) => {
    doc.visibilityState = state;
    doc.dispatchEvent(new Event('visibilitychange'));
};

describe('session keep-alive', () => {
    it('pings every 60 seconds while visible', () => {
        startSessionKeepAlive(ping, doc);
        expect(ping).not.toHaveBeenCalled();
        vi.advanceTimersByTime(KEEP_ALIVE_INTERVAL_MS);
        expect(ping).toHaveBeenCalledTimes(1);
        vi.advanceTimersByTime(KEEP_ALIVE_INTERVAL_MS * 2);
        expect(ping).toHaveBeenCalledTimes(3);
    });

    it('pauses while hidden and resumes when visible again', () => {
        startSessionKeepAlive(ping, doc);
        setVisibility('hidden');
        vi.advanceTimersByTime(KEEP_ALIVE_INTERVAL_MS * 5);
        expect(ping).not.toHaveBeenCalled();
        setVisibility('visible');
        vi.advanceTimersByTime(KEEP_ALIVE_INTERVAL_MS);
        expect(ping).toHaveBeenCalledTimes(1);
    });

    it('does not start when the page begins hidden', () => {
        doc.visibilityState = 'hidden';
        startSessionKeepAlive(ping, doc);
        vi.advanceTimersByTime(KEEP_ALIVE_INTERVAL_MS * 2);
        expect(ping).not.toHaveBeenCalled();
    });

    it('does not double up on repeated visible events', () => {
        startSessionKeepAlive(ping, doc);
        setVisibility('visible');
        vi.advanceTimersByTime(KEEP_ALIVE_INTERVAL_MS);
        expect(ping).toHaveBeenCalledTimes(1);
    });

    it('stops pinging and ignores visibility after teardown', () => {
        const stop = startSessionKeepAlive(ping, doc);
        stop();
        setVisibility('visible');
        vi.advanceTimersByTime(KEEP_ALIVE_INTERVAL_MS * 2);
        expect(ping).not.toHaveBeenCalled();
    });
});
