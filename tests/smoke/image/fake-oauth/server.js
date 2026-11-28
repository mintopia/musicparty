'use strict';

const http = require('node:http');
const crypto = require('node:crypto');
const {URL} = require('node:url');

const port = Number(process.env.PORT || 9000);
const identities = ['alice', 'bob', 'carol', 'dave'];
const codes = new Map();
const tokens = new Map();
let issued = 0;

function json(res, status, body) {
    res.writeHead(status, {'Content-Type': 'application/json'});
    res.end(JSON.stringify(body));
}

function readBody(req) {
    return new Promise((resolve) => {
        const chunks = [];
        req.on('data', (chunk) => chunks.push(chunk));
        req.on('end', () => resolve(Buffer.concat(chunks).toString()));
    });
}

const server = http.createServer(async (req, res) => {
    const url = new URL(req.url, `http://${req.headers.host}`);

    if (url.pathname === '/oauth/authorize') {
        const name = identities[issued % identities.length];
        issued += 1;
        const code = crypto.randomBytes(8).toString('hex');
        codes.set(code, name);
        const target = new URL(url.searchParams.get('redirect_uri'));
        target.searchParams.set('code', code);
        target.searchParams.set('state', url.searchParams.get('state') || '');
        res.writeHead(302, {Location: target.toString()});
        res.end();
        return;
    }

    if (url.pathname === '/oauth/token' && req.method === 'POST') {
        const params = new URLSearchParams(await readBody(req));
        const name = codes.get(params.get('code'));
        if (!name) {
            json(res, 400, {error: 'invalid_grant'});
            return;
        }
        const accessToken = crypto.randomBytes(12).toString('hex');
        tokens.set(accessToken, name);
        json(res, 200, {access_token: accessToken, refresh_token: 'refresh', expires_in: 3600, token_type: 'Bearer'});
        return;
    }

    if (url.pathname === '/api/user') {
        const name = tokens.get((req.headers.authorization || '').replace(/^Bearer /, ''));
        if (!name) {
            json(res, 401, {error: 'unauthenticated'});
            return;
        }
        json(res, 200, {id: `smoke-${name}`, name, nickname: name, email: `${name}@smoke.test`, email_verified_at: '2026-01-01T00:00:00Z'});
        return;
    }

    json(res, 404, {error: 'not found'});
});

server.listen(port, '0.0.0.0', () => console.log(`fake oauth listening on ${port}`));
