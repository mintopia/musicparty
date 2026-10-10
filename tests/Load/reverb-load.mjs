import { createHash, createHmac } from 'node:crypto';

const cfg = {
    parties: Number(process.env.PARTIES ?? 25),
    connsPerParty: Number(process.env.CONNS_PER_PARTY ?? 40),
    seconds: Number(process.env.SECONDS ?? 60),
    perPartyPerSecond: Number(process.env.RATE ?? 1),
    payloadBytes: Number(process.env.PAYLOAD_BYTES ?? 4000),
    host: process.env.REVERB_HOST ?? '127.0.0.1',
    port: Number(process.env.REVERB_PORT ?? 8080),
    appId: process.env.REVERB_APP_ID ?? 'loadtest',
    key: process.env.REVERB_APP_KEY ?? 'loadkey',
    secret: process.env.REVERB_APP_SECRET ?? 'loadsecret',
};

const latencies = [];
const timeline = {};
const bucket = (k) => { const s = Math.floor((Date.now() - startedAt) / 1000); timeline[s] ??= { sent: 0, ok: 0, recv: 0 }; timeline[s][k]++; };
const startedAt = Date.now();
let received = 0;
let connected = 0;
let failed = 0;
const sockets = [];

function connect(party) {
    return new Promise((resolve) => {
        const ws = new WebSocket(`ws://${cfg.host}:${cfg.port}/app/${cfg.key}`);
        const timer = setTimeout(() => { failed++; resolve(); }, 20000);
        ws.onmessage = (m) => {
            const msg = JSON.parse(m.data);
            if (msg.event === 'pusher:connection_established') {
                ws.send(JSON.stringify({ event: 'pusher:subscribe', data: { channel: `party.${party}` } }));
            } else if (msg.event === 'pusher_internal:subscription_succeeded') {
                clearTimeout(timer);
                connected++;
                resolve();
            } else if (msg.event === 'queue.load') {
                received++;
                bucket('recv');
                latencies.push(Date.now() - JSON.parse(msg.data).sentAt);
            } else if (msg.event === 'pusher:ping') {
                ws.send(JSON.stringify({ event: 'pusher:pong', data: {} }));
            }
        };
        ws.onerror = () => { clearTimeout(timer); failed++; resolve(); };
        sockets.push(ws);
    });
}

async function publish(party) {
    const body = JSON.stringify({
        name: 'queue.load',
        channels: [`party.${party}`],
        data: JSON.stringify({ sentAt: Date.now(), pad: 'x'.repeat(cfg.payloadBytes) }),
    });
    const params = {
        auth_key: cfg.key,
        auth_timestamp: Math.floor(Date.now() / 1000),
        auth_version: '1.0',
        body_md5: createHash('md5').update(body).digest('hex'),
    };
    const query = Object.entries(params).map(([k, v]) => `${k}=${v}`).sort().join('&');
    const path = `/apps/${cfg.appId}/events`;
    const sig = createHmac('sha256', cfg.secret).update(`POST\n${path}\n${query}`).digest('hex');
    const res = await fetch(`http://${cfg.host}:${cfg.port}${path}?${query}&auth_signature=${sig}`, {
        method: 'POST', headers: { 'content-type': 'application/json' }, body,
    });
    return res.ok;
}

const pct = (a, p) => a.length ? a[Math.min(a.length - 1, Math.floor(a.length * p))] : null;

const t0 = Date.now();
const jobs = [];
for (let p = 0; p < cfg.parties; p++) {
    for (let c = 0; c < cfg.connsPerParty; c++) jobs.push(() => connect(`LOAD${p}`));
}
for (let i = 0; i < jobs.length; i += 50) {
    await Promise.all(jobs.slice(i, i + 50).map((j) => j()));
}
const connectSecs = (Date.now() - t0) / 1000;

let published = 0;
let publishFailed = 0;
const end = Date.now() + cfg.seconds * 1000;
await new Promise((resolve) => {
    const timer = setInterval(async () => {
        if (Date.now() >= end) { clearInterval(timer); resolve(); return; }
        for (let p = 0; p < cfg.parties; p++) {
            for (let r = 0; r < cfg.perPartyPerSecond; r++) {
                bucket('sent');
                publish(`LOAD${p}`).then((ok) => { ok ? (published++, bucket('ok')) : publishFailed++; }).catch(() => publishFailed++);
            }
        }
    }, 1000);
});
await new Promise((r) => setTimeout(r, 3000));

latencies.sort((a, b) => a - b);
const expected = published * cfg.connsPerParty;
console.log(JSON.stringify({
    config: cfg,
    connectionsRequested: cfg.parties * cfg.connsPerParty,
    connected,
    connectFailures: failed,
    connectSeconds: connectSecs,
    published,
    publishFailed,
    expectedDeliveries: expected,
    received,
    lossPct: expected ? +(100 * (1 - received / expected)).toFixed(3) : null,
    deliveriesPerSecond: +(received / cfg.seconds).toFixed(0),
    latencyMs: { p50: pct(latencies, 0.5), p95: pct(latencies, 0.95), p99: pct(latencies, 0.99), max: latencies.at(-1) ?? null },
}, null, 2));
if (process.env.TIMELINE) { console.error(Object.entries(timeline).map(([k, v]) => `${k}s sent=${v.sent} ok=${v.ok} recv=${v.recv}`).join('\n')); }
sockets.forEach((s) => s.close());
process.exit(0);
