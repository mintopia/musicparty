# Websockets share the app hostname and the browser connects to its own origin

Reverb used to be reached on its own hostname and port, which the front end learned from `VITE_REVERB_*` build-time variables. That made every deployment configure two public endpoints, two certificates and a second proxy route, and it baked the host into the built assets.

We serve websockets from the same hostname as the app. The Caddyfile in the app container matches upgrade requests (`Connection` contains `Upgrade` and `Upgrade: websocket`) and reverse-proxies them to Reverb; every other request goes to Laravel unchanged. The upstream comes from `REVERB_UPSTREAM_HOST` and `REVERB_UPSTREAM_PORT`, defaulting to the internal `reverb` service on port 8080, so dev and production share one Caddyfile. The browser connects to `window.location`'s origin, so no host, port or scheme needs configuring for it.

Server-side publishing from the app to Reverb keeps using `REVERB_HOST`/`REVERB_PORT`/`REVERB_SCHEME` on the internal network and never leaves it.

An Operator's own reverse proxy in front of the app only has to forward one hostname with websocket upgrade headers. The cost is that Reverb traffic passes through the app container's Caddy, which is acceptable at this scale.
