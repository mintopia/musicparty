<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <link href="/css/app.css" rel="stylesheet">
        <link href="/css/tv.css" rel="stylesheet">

        <title>
            Music Party
            @if (isset($title) && is_array($title))
                - {{ implode(' - ', $title) }}
            @elseif (isset($title))
                - {{ $title }}
            @endif
        </title>
    </head>
    <body class="bg-dark cover-bg p-0 overflow-hidden">
        <div id="app">
            @yield('content')
        </div>
        @include('partials._javascript')
        <script src="/js/app.js"></script>
        @yield('footer')
    </body>
</html>
