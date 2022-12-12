# Music Party

## Introduction

A collaborative party music jukebox using Spotify.

## Planned Features

This is still a VERY early alpha, with about 6 days dev work on it. It is barely functional and doesn't have any features like deleting upcoming tracks.

The following features are planned:

 - TV Mode
 - A UI that actually looks good and is usable - Getting there!
 - Extra description/messaging
 - Much better descriptions
 - User banning/limiting
 - Allow/Deny lists for tracks, albums, etc.
 - Export playback history to another playlist
 - Discord role/presence verification
 - Automatic Playlist Reinitialisation
 - Autoplay Mode - forces playback on the specified device
 - Spotify Controls

## Installation

An example deployment in production would be something like this for a `.env` file.

```

HOSTNAME=musicparty.example.com
PROTOCOL=http://
PORT=80


APP_URL=${PROTOCOL}${HOSTNAME}
#App key should be any valid, either use php to generate this or https://generate-random.org/laravel-key-generator
APP_KEY=


DB_DATABASE=musicparty
DB_USERNAME=musicparty
#Add you own dbpassword here for security
DB_PASSWORD=

#
PUSHER_APP_KEY=musicparty
#Generate a new secret here using UUID4 (https://generate-random.org/uuid-generator)
PUSHER_APP_SECRET=
#
MIX_PUSHER_CLIENT_HOSTNAME=${HOSTNAME}
MIX_PUSHER_CLIENT_PORT=${PORT}

#Create two new apps from spotify.
#You will need to set the redirect uri to ${PROTOCOL}${HOSTNAME}/auth/spotify/search/redirect when you create them
SPOTIFY_CLIENT_ID=
SPOTIFY_CLIENT_SECRET=

SPOTIFY_SEARCH_CLIENT_ID=
SPOTIFY_SEARCH_CLIENT_SECRET=
SPOTIFY_SEARCH_REDIRECT_URI=${APP_URL}/auth/spotify/search/redirect
#UI required for this, available in debug output, will fall back to SPOTIFY_CLIENT_ID if not set
SPOTIFY_SEARCH_REFRESH_TOKEN=

#Create a new discord app at https://discord.com/developers/applications
#Set the redirect uri in the OAuth2 settings to ${PROTOCOL}${HOSTNAME}/auth/discord/search/redirect
DISCORD_CLIENT_ID=
#OAuth2 Secret from the OAuth2 settings in Discord
DISCORD_CLIENT_SECRET=

```

The defaults for other env settings are based on a docker setup similar to the one below and for running in production.

You would then a docker-compose something like this:

```yaml
version: '3'
services:
    nginx:
        image: ghcr.io/mintopia/musicparty-nginx:develop
        ports:
            - ${PORT}:80
        restart: unless-stopped
        depends_on:
            - php-fpm

    php-fpm:
        image: ghcr.io/mintopia/musicparty-php-fpm:develop
        env_file: .env
        restart: unless-stopped
        depends_on:
            - db
            - redis

    scheduler:
        image: ghcr.io/mintopia/musicparty-php-fpm:develop
        entrypoint: [ "php" ]
        command: "artisan schedule:work"
        user: "1000"
        env_file: .env
        restart: unless-stopped
        depends_on:
            - db
            - redis

    worker:
        image: ghcr.io/mintopia/musicparty-php-fpm:develop
        entrypoint: [ "php" ]
        command: "artisan queue:work"
        user: "1000"
        env_file: .env
        restart: unless-stopped
        scale: 2
        depends_on:
            - db
            - redis

    websockets:
        image: ghcr.io/mintopia/musicparty-php-fpm:develop
        ports:
            - 6001:6001
        entrypoint: [ "php" ]
        command: "artisan websockets:serve"
        user: "1000"
        env_file: .env
        restart: unless-stopped
        depends_on:
            - db
            - redis

    artisan:
        image: ghcr.io/mintopia/musicparty-php-fpm:develop
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

By default the stack will now be running on `http://localhost:port`.

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
