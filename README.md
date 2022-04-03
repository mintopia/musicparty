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

No docker-compose and docker images yet. Instead it's a manual installation.

1. Create a MariaDB/MySQL DB
2. Create Spotify and Discord applications for auth. Make a note of client IDs and Secrets and configure redirect URIs.
3. Copy the `example.env` to `.env`
4. Set the DB credentials, Spotify and Discord IDs and make sure app URL is correct for Spotify and Discord.
5. Run `php artisan key:generate` and `php artisan migrate`. This adds an application key for encrypting client tokens and sets up the database schema.
6. Run the site using whatever PHP way you want or use `php artisan serve` to run a local site
7. Go to the site and login. The first user is made admin and will have permission to create a party.
8. Create a party
9. Run the daemon to update party status every 15 seconds using `php artisan party:daemon`.

## Usage

When it's running, it will create a managed Spotify playlist called something like `Spotify Party - ABCD`. Start playing this playlist and it'll handle the rest.

## Known Issues

After a while, Spotify stops updating the Playlist on clients - the playlist is updated, but the clients don't get told the changes. When this happens, the only way to resolve it is to create a new playlist. You can do this using `php artisan party:fixplaylist <code>`.

## Development

I'm terrible at UI/UX, if anyone would like to contribute to make this look good and knows Vue/Blade/Laravel, please dive in and help. Collaboration is on the UK LAN Techs discord.

## Thanks

This would not exist without the support of the following:

- UK LAN Techs
- Jason 'Mohero' Rivers

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
