<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>

    @vite(['resources/js/inertia.ts'])
    @if($currentTheme)
        <link rel="stylesheet" href="{{ $currentTheme->stylesheetUrl() }}">
    @endif
    @inertiaHead
</head>
<body @if($darkMode) data-bs-theme="dark" @endif>
@inertia
</body>
</html>
