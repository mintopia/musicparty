import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {bindRealtimeResync, RESYNC_EVENT} from '../lib/realtimeResync';

let stateChange;
let handler;
let doc;

const echo = () => ({
    connector: {pusher: {connection: {bind: (event, cb) => { stateChange = cb; }}}},
});

beforeEach(() => {
    handler = vi.fn();
    window.addEventListener(RESYNC_EVENT, handler);
    doc = new EventTarget();
    doc.visibilityState = 'hidden';
});

afterEach(() => {
    window.removeEventListener(RESYNC_EVENT, handler);
});

describe('realtime resync', () => {
    it('does not fire on the first connect', () => {
        bindRealtimeResync(echo(), window, doc);
        stateChange({previous: 'connecting', current: 'connected'});
        expect(handler).not.toHaveBeenCalled();
    });

    it('fires once when unavailable reconnects to connected', () => {
        bindRealtimeResync(echo(), window, doc);
        stateChange({previous: 'connecting', current: 'connected'});
        stateChange({previous: 'connected', current: 'unavailable'});
        stateChange({previous: 'unavailable', current: 'connecting'});
        expect(handler).not.toHaveBeenCalled();
        stateChange({previous: 'connecting', current: 'connected'});
        expect(handler).toHaveBeenCalledTimes(1);
    });

    it('fires once when the page becomes visible, not when hidden', () => {
        bindRealtimeResync(echo(), window, doc);
        doc.dispatchEvent(new Event('visibilitychange'));
        expect(handler).not.toHaveBeenCalled();
        doc.visibilityState = 'visible';
        doc.dispatchEvent(new Event('visibilitychange'));
        expect(handler).toHaveBeenCalledTimes(1);
    });

    it('tolerates the noop Echo stub without a connector', () => {
        expect(() => bindRealtimeResync({channel: () => ({})}, window, doc)).not.toThrow();
    });
});
