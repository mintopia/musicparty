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

Horizon and Telescope are installed and enabled, with access limited to the admin role. The application itself is
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

In the `example` directory there is a docker compose file and some .env example files. These are for the setup I use.
Just rename the .env files and edit them accordingly. You can get a [random Laravel application key here](https://generate-random.org/laravel-key-generator).

You need to expose the `musicparty` and `reverb` containers to the public. They are both configured to listen on port 80
in the docker compose, so you probably want something like Traefik or Caddy in-front as a reverse proxy.

I'm running this with an external docker network called `frontend` with Caddy running as HTTP/HTTPS ingress. You will
need to add a network section for the `reverb` and `musicparty` services to add them to the `frontend` network if you
want to do this.

You will need to make a logs directory and chmod it 777 as I still need to sort permissions out.

To bring up the site, run the following:

```bash
docker compose up -d redis database
docker compose run --rm artisan migrate
docker compose run --rm artisan db:seed
docker compose run --rm artisan setup:discord
docker compose up -d
```

You should now be able to visit the site and login. From here you can use the admin menu to configure the site.

## Usage Tips

The best way to play the party is to use the Web Player in the menu. This uses the Web Playback SDK to register itself
as a player device and then start playing music in that browser tab. It will then update the party when the track
changes. This means that you can disable polling if you're using the Web Player.

Music Party attempts to keep a Spotify playlist up-to-date, and it's possible to use it by just playing the playlist but
this can cause some issues and isn't that reliable. The party option to add tracks to the queue will also add the tracks
immediately into the Spotify playback queue.

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

Observability using OpenTelemetry is a WIP, I need to change how the docker images are built to have some intermediate
images with the right PHP extensions as it takes forever to build grpc and protobuf.

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
