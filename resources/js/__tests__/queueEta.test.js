import {describe, expect, it} from 'vitest';
import {estimateQueue} from '../lib/queueEta';

const item = (id, duration_ms) => ({id, track: {duration_ms}});
const START = Date.parse('2026-10-10T12:00:00Z');

describe('estimateQueue', () => {
    it('chains start times after the now playing track in deterministic mode', () => {
        const result = estimateQueue({
            startedAt: '2026-10-10T12:00:00Z',
            nowPlaying: item(1, 200000),
            upNext: item(2, 100000),
            queue: [item(3, 50000), item(4, 30000)],
            mode: 'deterministic',
            now: START + 60000,
        });
        expect(result.upNextAt).toBe(START + 200000);
        expect(result.startsAt).toEqual({2: START + 200000, 3: START + 300000, 4: START + 350000});
        expect(result.totalMs).toBe(180000);
    });

    it('omits per-position estimates in weighted mode', () => {
        const result = estimateQueue({startedAt: '2026-10-10T12:00:00Z', nowPlaying: item(1, 200000), upNext: item(2, 100000), queue: [item(3, 50000)], mode: 'weighted', now: START});
        expect(result.upNextAt).toBe(START + 200000);
        expect(result.startsAt).toEqual({});
        expect(result.totalMs).toBe(150000);
    });

    it('starts now when nothing is playing', () => {
        const result = estimateQueue({startedAt: null, nowPlaying: null, upNext: item(2, 1000), queue: [item(3, 1000)], mode: 'deterministic', now: START});
        expect(result.startsAt).toEqual({2: START, 3: START + 1000});
    });

    it('clamps an overrunning track to now', () => {
        const result = estimateQueue({startedAt: '2026-10-10T12:00:00Z', nowPlaying: item(1, 1000), upNext: item(2, 1000), queue: [], mode: 'deterministic', now: START + 9000});
        expect(result.upNextAt).toBe(START + 9000);
    });

    it('gives no estimate when the start time is unknown', () => {
        const result = estimateQueue({startedAt: null, nowPlaying: item(1, 1000), upNext: item(2, 1000), queue: [item(3, 500)], mode: 'deterministic', now: START});
        expect(result.upNextAt).toBeNull();
        expect(result.startsAt).toEqual({});
        expect(result.totalMs).toBe(1500);
    });

    it('gives no estimate without an up next entry', () => {
        const result = estimateQueue({startedAt: null, nowPlaying: null, upNext: null, queue: [], mode: 'deterministic', now: START});
        expect(result).toEqual({upNextAt: null, totalMs: 0, startsAt: {}});
    });
});
