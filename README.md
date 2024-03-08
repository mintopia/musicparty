# Music Party

## Introduction

Version 2 of Music Party - the amount of changes and new features meant a major refactor was in-order.

### Social Provider Support

 - Discord
 - Steam (No authentication, just account linking)
 - Twitch
 - Laravel Passport

These are using Laravel Socialite, so any provider supported by Socialite can be integrated.

## Setup

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

I use the following docker-compose for running this in production:

```yaml
version: '3'
services:
  nginx:
    image: ghcr.io/mintopia/musicparty-nginx:develop
    env_file: .env.nginx
    restart: unless-stopped
    depends_on:
      - php-fpm
    networks:
      - frontend
      - default
    volumes:
      - ./public:/var/www/storage/public

  php-fpm:
    image: ghcr.io/mintopia/musicparty-php-fpm:develop
    env_file: .env
    restart: unless-stopped
    depends_on:
      - redis
      - database
    volumes:
      - ./logs:/var/www/storage/logs
      - ./public:/var/www/storage/public

  redis:
    image: redis:6.2.6
    restart: unless-stopped

  database:
    image: mariadb:10.5-focal
    env_file: .env.mariadb
    restart: unless-stopped
    volumes:
      - ./database:/var/lib/mysql

  worker:
    image: ghcr.io/mintopia/musicparty-php-fpm:develop
    restart: unless-stopped
    deploy:
      replicas: 2
    env_file: .env
    depends_on:
      - database
      - redis
    volumes:
      - ./logs:/var/www/storage/logs
      - ./public:/var/www/storage/public
    entrypoint: ['php']
    command: 'artisan queue:work'


  scheduler:
    image: ghcr.io/mintopia/musicparty-php-fpm:develop
    restart: unless-stopped
    env_file: .env
    depends_on:
      - database
      - redis
    volumes:
      - ./logs:/var/www/storage/logs
      - ./public:/var/www/storage/public
    entrypoint: ['php']
    command: 'artisan schedule:work'

  artisan:
    image: ghcr.io/mintopia/musicparty-php-fpm:develop
    profiles:
      - artisan
    env_file: .env
    depends_on:
      - database
      - redis
    volumes:
      - ./logs:/var/www/storage/logs
      - ./public:/var/www/storage/public
    entrypoint: ['php', 'artisan']

networks:
  frontend:
    external: true
```

I'm running with an external docker network called `frontend` with Caddy running as HTTP/HTTPS ingress. To bring up the site, run the following:


```bash
# Create your docker compose file
# Create your .env file from the project's .env.example and edit as required.
docker compose up -d redis database
docker compose run --rm artisan key:generate
docker compose run --rm artisan migrate
docker compose run --rm artisan db:seed
docker compose run --rm artisan control:setup-discord
docker compose up -d
```

You should now be able to visit the site and login. From here you can use the admin menu to configure the site.

## Contributing

It's an open source project and I'm happy to accept pull requests. I am terrible at UI and UX, which is why this is entirely using server-side rendering. If someone wants to use Vue/Laravel Livewire - please go ahead!

## Roadmap

The following features are on the roadmap:

 - Better UI/UX. I'm currently using [tabler.io](https://tabler.io) and mostly server-side rendering, with some Vue components.
 - Unit Tests. This was very rapidly developed, I'm sorry!
 - PHPCS and PHPStan. Should be aiming for PSR-12 and level 8 PHPStan.

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
