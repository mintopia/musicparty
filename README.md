# Music Party

## Introduction

Version 2 of Music Party - the amount of changes and new features meant a major refactor was in-order.

### Social Provider Support

 - Discord
 - Steam (No authentication, just account linking)
 - Twitch
 - Laravel Passport

These are using Laravel Socialite, so any provider supported by Socialite can be integrated.

## Technology

This project is written in PHP 8.4 using the Laravel 12 framework. It was migrated from Laravel 10 and 11 so has some
legacy project structure - but this is the intended upgrade path.

Horizon and Pulse are installed and enabled, with access limited to the admin role. The application itself is
served using Laravel Octane and FrankenPHP.

Websocket communications are handled using Laravel Reverb.

## Development Setup

You will need to create a Discord application and have the Client ID and Client Secret available.

```bash
cp .env.example .env
docker compose up -d redis db
docker compose run --rm composer install
docker compose run --rm artisan key:generate
docker compose run --rm artisan migrate
docker compose run --rm artisan db:seed
docker compose run --rm artisan control:setup-discord
docker compose run --rm npm install
docker compose run --rm npm run build
docker compose up -d
```

Add the redirect URLs from the `control:setup-discord` step to your Discord OAuth2 configuration.

You should now be able to login. The first user will be given the admin role.

## Production Deployment

Music Party runs on a single host as one image (`ghcr.io/mintopia/musicparty`) started as several services. See
[`docker-compose.production.example.yml`](docker-compose.production.example.yml) for a complete, maintained example,
and [ADR 0004](docs/adr/0004-single-hostname-websockets-same-origin.md) for why everything shares one hostname.

### Topology

| Service     | Purpose                                                                  |
|-------------|--------------------------------------------------------------------------|
| `app`       | Web app (FrankenPHP/Octane). Also proxies websocket upgrades to `reverb` |
| `reverb`    | Websocket server. Not published; reached only through `app`              |
| `horizon`   | Queue workers                                                            |
| `scheduler` | Runs scheduled tasks                                                     |
| `database`  | MariaDB                                                                  |
| `redis`     | Cache, queues and sessions                                               |

Copy the compose file to `docker-compose.yml`, create a `.env` from `docker/production/production.env`, set your
application key (a [random Laravel key](https://generate-random.org/laravel-key-generator) works) and `APP_URL`, then:

```bash
docker compose up -d database redis
docker compose run --rm --entrypoint php app artisan migrate
docker compose run --rm --entrypoint php app artisan db:seed
docker compose run --rm --entrypoint php app artisan setup:discord
docker compose up -d
```

You should now be able to visit the site and login. The first user is given the admin role; use the admin menu to
configure the site.

### Reverse proxy

Only the `app` container is exposed. Put your own reverse proxy (Caddy, Traefik, nginx...) in front of it and forward a
single hostname to it. The proxy must:

 - Pass the `Upgrade` and `Connection` headers through, so websocket connections reach the app.
 - Allow long-lived connections. Websockets stay open for the whole party, so make sure read/idle timeouts are long
   enough (or disabled); a short idle timeout will drop clients periodically and force them to reconnect.

Caddy example:

```
musicparty.example.com {
  reverse_proxy musicparty-app-1:80
}
```

Caddy forwards upgrade headers automatically. For nginx, set `proxy_http_version 1.1`, `proxy_set_header Upgrade
$http_upgrade`, `proxy_set_header Connection "upgrade"` and a large `proxy_read_timeout`.

### Encore relay URL

Encore connects to an instance's Pusher-compatible relay on the same hostname as the app:

```
wss://<app host>/app/<REVERB_APP_KEY>
```

## Usage Tips

The best way to play the party is to use the Web Player in the menu. This uses the Web Playback SDK to register itself
as a player device and then start playing music in that browser tab. It will then update the party when the track
changes. This means that you can disable polling if you're using the Web Player.

The Spotify API is very bad and unreliable. The queue API is particularly bad. Spotify also insists on wanting to be
playing things within a context (album, artist, playlist), although it's not required. This means that Music party can
cause the desktop client to be very 'weird'.

If you're not using the Web Player, you will need to enable polling in the party. You should also start the playback by
selecting a single song from the search in Spotify, or create a playlist with a single track and play that. I've made
[a playlist for that purpose](https://open.spotify.com/playlist/64KYRp7xWZFH9iRSgJrSAh?si=24d0a503092c4e1b).

Because of various oddities, when you start playing Music Party, it may take a few tracks to sort itself out. Stick with
it, it'll eventually catch up.

## Troubleshooting

Everything should be logged to the normal Laravel logger, so you should be able to diagnose things based on the logs. If
you are still having issues, there's some config options in `.env` that you can try.

- `MUSICPARTY_ALLOW_OVERLAPPING_UPDATES=false` - Normally we don't allow party updates to run simultaneously, but this
  may cause issues with some deployments. Setting this to false turns off this behaviour.
- `MUSICPARTY_WEBHOOK_DISPATCH_AFTER_REQUEST=false` - When using the Web Player, it will trigger a Party Update after
  request finishes. Set this to false to trigger the update asynchronously immediately before the request has finished.
- `MUSICPARTY_WEBHOOK_SHOULD_QUEUE=false` - When we trigger a Party update from the webhook, it's processed
  asynchronously a a job. If this is causing problems, setting this to false will cause it to do the entire party
  update synchronously in the request.

## Observability

The docker images from the project have the Open Telemetry PHP extension and the project includes the appropriate Open
Telemetry libraries from Packagist. You can use Protobuf or GRPC transport if needed. You should just be able to
configure it using ENV variables and run a collector container. eg.

```dotenv
OTEL_PHP_AUTOLOAD_ENABLED=true
OTEL_SERVICE_NAME=musicparty
OTEL_EXPORTER_OTLP_ENDPOINT=http://collector:4317
```

The docker container already sets `OTEL_PHP_EXCLUDED_URLS` to remove common URLs that are used a lot and add no value
to monitoring. The default value is:

```dotenv
OTEL_PHP_EXCLUDED_URLS="pulse,horizon/.*,api/v1/ping,_ignition/.*,_debugbar/.*"
```

For the container:

```yaml
  collector:
    image: otel/opentelemetry-collector-contrib
    volumes:
      - ./collector.yml:/etc/otelcol-contrib/config.yaml
```

And finally the config in `collector.yml`, adapt this as you need.

```yaml
receivers:
  otlp:
    protocols:
      grpc:
        endpoint: 0.0.0.0:4317
      http:
        endpoint: 0.0.0.0:4318

processors:
  batch:

exporters:
  debug:

service:
  pipelines:
    traces:
      receivers: [otlp]
      processors: [batch]
      exporters: [debug]
    metrics:
      receivers: [otlp]
      processors: [batch]
      exporters: [debug]
    logs:
      receivers: [otlp]
      processors: [batch]
      exporters: [debug]
```

## Modding Support

Music Party now supports mods! Simply write a service provider to provide commands and event listeners. There are some
classes to help with this:

- `Mod` - Write an install method to create a Mod object for your mod. It should have a unique code.
- `ModSetting` - Similar to provider settings, it allows you to define configuration for your mod. By setting it to
  `private` you can use it as a variable to store data for your mod.
- `PartyModSetting` - A value of a setting for the party.

The best way to work with mod settings in the context of a party is using the helper methods on the `Party` class, eg.

```php
$isEnabled = $party->getModSettingValue('testmod', 'enabled');
$party->setModSetting('testmod', 'enabled', true);
```

There are new events that are emitted when a mod setting is updated or deleted for a party, and events emitted every
minute, hour and day.

### Whamageddon

The first example mod. When enabled, it will add Last Christmas by Wham into the queue. Every hour between 9AM and 1AM
an upvote will be added to the track.

## Contributing

It's an open source project and I'm happy to accept pull requests. I am terrible at UI and UX, which is why this is
entirely using server-side rendering. If someone wants to use Vue/Laravel Livewire - please go ahead!

## Roadmap

The following features are on the roadmap:

 - Better UI/UX. I'm currently using [tabler.io](https://tabler.io) and mostly server-side rendering, with some Vue components.
 - Unit Tests. This was very rapidly developed, I'm sorry!
 - PHPCS and PHPStan. Should be aiming for PSR-12 and level 8 PHPStan.
 - Update the existing webplayer to have full Spotify playback controls

## Thanks

This would not exist without the support of the following:

- UK LAN Techs
- Moogle

## License

The MIT License (MIT)

Copyright (c) 2024 Jessica Smith

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
