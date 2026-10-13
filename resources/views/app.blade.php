<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>

    @include('partials._pusher-config')

    @vite(['resources/js/inertia.ts'])
    @include('partials._theme')
    @inertiaHead
</head>
<body @if($darkMode) data-bs-theme="dark" @endif>
@inertia
</body>
</html>
