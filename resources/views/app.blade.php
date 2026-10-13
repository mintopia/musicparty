<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>

    @include('partials._pusher-config')

    @vite(['resources/css/app.css', 'resources/js/inertia.ts'])
    @inertiaHead
</head>
<body>
@inertia
</body>
</html>
