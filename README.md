# Music Party

## Introduction

A collaborative party music jukebox using Spotify.

## Planned Features

This is still a VERY early alpha, with about 2 days dev work on it. It is barely functional and doesn't have any features like deleting upcoming tracks.

The following features are planned:

 - TV Mode
 - Verifying unique party codes
 - Deleting upcoming songs
 - A UI that actually looks good and is usable
 - Extra description/messaging
 - Much better descriptions
 - User banning/limiting
 - Allow/Deny lists for tracks, albums, etc.
 - Export playback history to another playlist
 - Discord role/presence verification
 - Websocket/Pusher based status updates to browser
 - Automatic Playlist Reinitialisation
 - More frequent polling at track transition boundaries
 - Autoplay Mode - forces playback on the specified device
 - Spotify Controls

## Installation

No CI/CD yet for building images, but an example deployment in production would be a .env file looking like this:

```
APP_KEY=
APP_URL=https://musicparty.example.com

DB_DATABASE=musicparty
DB_USERNAME=musicparty
DB_PASSWORD=

PUSHER_APP_KEY=
PUSHER_APP_SECRET=

SPOTIFY_CLIENT_ID=
SPOTIFY_CLIENT_SECRET=

DISCORD_CLIENT_ID=
DISCORD_CLIENT_SECRET=

```

The defaults for other env settings are based on a docker setup similar to the one below and for running in production.

You would then a docker-compose something like this:

```yaml
version: '3'
services:
  nginx:
    image: musicparty-nginx:latest
    ports:
      - 8000:8000
    links:
      - php-fpm
    restart: unless-stopped
    depends_on:
      - php-fpm

  php-fpm:
    image: musicparty-php-fpm:latest
    env_file: .env
    restart: unless-stopped
    depends_on:
      - db
      - redis

  daemon:
    image: musicparty-php-fpm:latest
    entrypoint: [ "php" ]
    command: "artisan party:daemon"
    user: "1000"
    env_file: .env
    restart: unless-stopped
    depends_on:
      - db
      - redis

  artisan:
      image: musicparty-php-fpm:latest
      entrypoint: [ "php", "artisan" ]
      user: "1000"
      env_file: .env
      depends_on:
          - db
          - redis
      profiles:
          - artisan

  redis:
    image: redis:6.0
    restart: unless-stopped

  db:
    image: mariadb:10.5-focal
    restart: unless-stopped
    environment:
      MYSQL_ROOT_PASSWORD: "${DB_PASSWORD}"
      MYSQL_DATABASE: "${DB_DATABASE}"
      MYSQL_USER: "${DB_USERNAME}"
      MYSQL_PASSWORD: "${DB_PASSWORD}"
    volumes:
      - dbdata:/var/lib/mysql

volumes:
  dbdata:
    driver: local
```

Run the stack with `docker-compose up -d` and the DB migrations using `docker-compose run --rm artisan migrate`.

## Usage

When it's running, it will create a managed Spotify playlist called something like `Spotify Party - ABCD`. Start playing this playlist and it'll handle the rest.

## Known Issues

After a while, Spotify stops updating the Playlist on clients - the playlist is updated, but the clients don't get told the changes. When this happens, the only way to resolve it is to create a new playlist. You can do this using `docker-compose run --rm artisan party:fixplaylist <code>`.

## Development

I'm terrible at UI/UX, if anyone would like to contribute to make this look good and knows Vue/Blade/Laravel, please dive in and help. Collaboration is on the UK LAN Techs discord.

There is a docker-compose and compose files for running a dev environment with the source code bind mounted. You can bring up the stack by doing the following:

1. Copy `.env.example` to `.env`
2. Edit `.env` as appropriate
3. Run `docker-compose up -d`
4. Run `docker-compose run --rm artisan migrate` to run DB migrations

By default the stack will now be running on `http://localhost:8000`.

A full set of typical PHP development tools are defined in docker-compose and can be run using the `docker-compose run --rm <tool>` syntax, eg. `artisan`, `composer`, `phpunit`.

## Thanks

This would not exist without the support of the following:

- UK LAN Techs
- Jason 'Mohero' Rivers
- Chris 'Cheez' Stretton
- Development Team Serenity at Fasthosts for the PHP build environment images

## License

The MIT License (MIT)

Copyright (c) 2022 Jessica Smith

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
