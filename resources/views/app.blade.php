<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        $site = app(\App\Domain\Admin\SiteSettings::class);
    @endphp
    <title>{{ $site->name() }}</title>
    @if ($site->fileUrl('favicon'))
        <link rel="icon" href="{{ $site->fileUrl('favicon') }}">
    @endif
    <script>window.siteName = @json($site->name());</script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $pusherConfig = [
            'appKey' => config('broadcasting.connections.reverb.key'),
            'host' => config('broadcasting.connections.reverb.options.host'),
            'port' => config('broadcasting.connections.reverb.options.port'),
            'scheme' => config('broadcasting.connections.reverb.options.scheme'),
        ];
    @endphp
    <script>window.pusherConfig = @json($pusherConfig);</script>
    <script>
        (function () {
            var scheme = @json(auth()->user()?->colour_scheme->value) || localStorage.getItem('colourScheme') || 'system';
            var dark = scheme === 'dark' || (scheme === 'system' && matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', dark);
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>{!! app(\App\Domain\Theming\Actions\RenderThemeCss::class)->handle() !!}</style>
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
