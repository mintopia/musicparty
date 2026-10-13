<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8"/>
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
        <meta http-equiv="X-UA-Compatible" content="ie=edge"/>

        @include('partials._pusher-config')

        @vite(['resources/js/app.ts'])

        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>
            @setting('name')
        </title>
        @if(App\Models\Setting::fetch('favicon'))
            <link rel="shortcut icon" href="@setting('favicon')"/>
        @endif
        @include('partials._theme')
    </head>
    <body class="vh-100 overflow-hidden m-0 p-0 bg-black">
        <div id="app">
            <youtube-player
                code="{{ $party->code }}"
            >
            </youtube-player>
        </div>
        @stack('footer')
    </body>
</html>
