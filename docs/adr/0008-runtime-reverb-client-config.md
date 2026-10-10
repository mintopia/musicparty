# Browser Reverb settings are injected at runtime, not built into the bundle

The app and the browser reach Reverb at different addresses. Inside Docker Compose the app publishes to `reverb:8080` over the internal network. Phones and the TV connect from outside, through the operator's reverse proxy, to something like `wss://music.example.com:443`. Today both use the same `REVERB_HOST/PORT/SCHEME`. `resources/views/app.blade.php` copies the internal `broadcasting.connections.reverb.options.*` values into `window.pusherConfig`, so a by-the-book install tells every browser to dial `ws://reverb:8080`. That name cannot be resolved outside Docker. The `VITE_REVERB_*` variables in `production.env` and `example/.env.example` are never read.

We considered fixing this with Vite build variables (`VITE_REVERB_HOST` and friends), which is what Laravel's installer scaffolds. We rejected that because the image is published once to GHCR and run by many operators. Whoever built it would fix the public host in the JavaScript, so every operator would need their own build.

Decision:
- The browser's Reverb settings (app key, host, port, scheme) come from server config at request time. The Blade root view writes them into `window.pusherConfig`, as v1 and v2 did. No `VITE_*` variable carries any of them, and CI never bakes them into the bundle.
- Public settings are separate from the internal publisher settings. `REVERB_PUBLIC_HOST`, `REVERB_PUBLIC_PORT` and `REVERB_PUBLIC_SCHEME` feed one config key, `broadcasting.connections.reverb.client`, which defaults to the host, port and scheme of `APP_URL`. `REVERB_HOST/PORT/SCHEME` stay the internal address the app publishes to (`reverb`, `8080`, `http` under Compose).
- The Reverb server listens on `REVERB_SERVER_PORT`, the same port the app publishes to. Compose files do not override it with `--port`.
- `bootstrap.js` creates Echo only when `window.pusherConfig.appKey` is set, so a missing key leaves the page working without realtime instead of blank.

Consequences: one image works for every operator, and the container start (`php artisan optimize`) caches whatever the environment says. The operator's reverse proxy must forward the Pusher paths (`/app`, `/apps`) on the public host to Reverb. A test checks that the public values, not the internal ones, reach the root view.
