# Music Party example deployment

A single-host Docker Compose setup: `web` (FrankenPHP/Octane), `horizon` (queues), `reverb` (websockets),
`scheduler`, `database` (MariaDB), `redis`, and a one-off `migrate` service that runs before the others.
Everything runs from the published image `ghcr.io/mintopia/musicparty`.

You need Docker with Compose v2.24 or newer, a public hostname, and a reverse proxy that terminates TLS.

## 1. Configure

```bash
cp .env.example .env
mkdir -p logs
```

Fill in `.env`. Generate the values like this:

```bash
# APP_KEY (no PHP needed on the host)
docker run --rm ghcr.io/mintopia/musicparty:latest php artisan key:generate --show
# or: echo "base64:$(openssl rand -base64 32)"

# DB_PASSWORD
openssl rand -hex 16

# REVERB_APP_ID, REVERB_APP_KEY, REVERB_APP_SECRET
shuf -i 100000000-999999999 -n 1
openssl rand -hex 10
openssl rand -hex 20
```

Set `APP_URL` to the public URL, for example `https://music.example.com` (no trailing slash). `APP_URL` is the single trusted public URL: URLs, redirects, the websocket host and the Steam
realm all derive from it, so only one hostname is supported and aliases are not. In production the app refuses to boot
if `APP_URL` is empty or points at localhost. The session cookie is secure only for an `https://` `APP_URL`, so plain
HTTP deployments work; set `SESSION_SECURE_COOKIE` only to override that, and a mismatch logs a boot warning. OAuth redirect URLs
are built from it.

Set `PUID` and `PGID` to your user and group (`id -u`, `id -g`). The containers run as that user, and the image
entrypoint hands `./logs` (mounted at `storage/logs`) to it on start, so the directory only has to exist before
the first `up`. If you create it as another user, it is re-owned to `PUID:PGID`.

Everything else has a working default in the image. To tune it, add variables to `.env`, for example
`LARAVEL_OCTANE_WORKERS=4` or `PHP_MEMORY_LIMIT=1G`. See <https://php.mintopia.net/usage/>.

## 2. Start

```bash
docker compose up -d
```

`migrate` waits for MariaDB and Redis, runs `php artisan migrate --force` and `php artisan db:seed --force`, then
exits. The other services start once it has finished successfully. The seeders are safe to re-run. Check with
`docker compose ps` and `docker compose logs migrate`.

The web container listens on `127.0.0.1:8080` only. Set `HTTP_PORT` in `.env` to use another host port.

## 3. Reverse proxy and TLS

Point your proxy at `127.0.0.1:8080` and serve a single hostname. Websockets use the same hostname: the web
container sends upgrade requests to `reverb` itself (ADR 0004), so there is no second hostname, port or
certificate. The proxy must pass `Upgrade` and `Connection` headers and allow long-lived connections. Caddy:

```
music.example.com {
  reverse_proxy 127.0.0.1:8080
}
```

For nginx set `proxy_http_version 1.1`, `proxy_set_header Upgrade $http_upgrade`,
`proxy_set_header Connection "upgrade"` and a large `proxy_read_timeout`. The container trusts `X-Forwarded-*` headers from any
proxy; `TRUSTED_PROXIES` is set only in the compose file's `environment` (never in `.env`). Narrow it there to your
proxy's address range if the container port is reachable by anything else.

### Encore (Soloist relay)

Encore is a server-side websocket client and sends no `Origin` header, so Reverb rejects it with pusher error 4009
"Origin not allowed" unless you set `REVERB_ALLOWED_ORIGINS=*` in `.env` (or list the origin it sends). The default
is the `APP_URL` host only. Encore's relay settings (key, secret, channel) come from the party's settings page on the
same instance it connects to. A key from another instance gives 4001 "Application does not exist".

## 4. Social login and Spotify

OAuth client credentials are not environment variables. They are stored in the database and set after the first
start. In the examples, replace `https://music.example.com` with your `APP_URL`.

Discord:

1. Create an application at <https://discord.com/developers/applications>, then open OAuth2.
2. Add these redirects:
   - `https://music.example.com/login/discord/return`
   - `https://music.example.com/profile/accounts/discord/return`
3. Enter the client ID and secret, and enable the provider:

   ```bash
   docker compose run --rm migrate php artisan setup:discord
   ```

Spotify:

1. Create an app at <https://developer.spotify.com/dashboard> and add these redirect URIs:
   - `https://music.example.com/login/spotify/return`
   - `https://music.example.com/profile/accounts/spotify/return`
   - `https://music.example.com/profile/accounts/spotifysearch/return`
2. Log in (next step), open the admin menu, go to the Spotify and Spotify Search social providers, enter the same
   client ID and secret in both, and enable them.

## 5. First admin

Open your site and log in with Discord. The first user created is given the admin role automatically; there is no
artisan command for it. Log in yourself before sharing the URL. Later admins are granted from the admin menu.

## Upgrading

```bash
docker compose pull
docker compose up -d
```

The new image recreates `migrate`, which applies migrations before the other services restart. The image is
`ghcr.io/mintopia/musicparty:latest` unless you set `MUSICPARTY_TAG` in `.env` (for example `3.0.0`) to pin a
release. Back up the database first:

```bash
docker compose exec database sh -c 'mariadb-dump -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"' > backup.sql
```

## Data

MariaDB and Redis use the named volumes `dbdata` and `redisdata`. Application logs go to stderr, so read them with `docker compose logs`; `./logs` holds only the file-based AI review log (`ai-review.log`). `docker compose down -v`
deletes the volumes and all data.

## Upgrade notes

- The linked-accounts migration is destructive: it deletes duplicate `linked_accounts` rows (same provider and external
  id), keeping the newest, then adds a unique index. Back up the database before running `php artisan migrate`.
- `APP_URL` must be set to your public URL in production; boot fails on an empty or localhost value.
- The session cookie's secure flag now follows the `APP_URL` scheme instead of the environment.
- `TRUSTED_PROXIES` is configured on the container (compose `environment`), not in `.env`.
