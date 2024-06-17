<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8"/>
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
        <meta http-equiv="X-UA-Compatible" content="ie=edge"/>

        <script>
            window.pusherConfig = {
                appKey: '{{ env('VITE_REVERB_APP_KEY') }}',
                host: '{{ env('VITE_REVERB_HOST') }}',
                port: {{ env('VITE_REVERB_PORT') }},
                scheme: '{{ env('VITE_REVERB_SCHEME') }}',
            };
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

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
            <tv-player
                code="{{ $party->code }}"
                initialstate='@json($party->getState())'
            >
            </tv-player>
        </div>
        @stack('footer')
    </body>
</html>
