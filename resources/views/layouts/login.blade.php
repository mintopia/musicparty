<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
    <meta http-equiv="X-UA-Compatible" content="ie=edge"/>
    <title>@setting('name', config('app.name'))</title>
    @if(App\Models\Setting::fetch('favicon'))
        <link rel="shortcut icon" href="@setting('favicon')"/>
    @endif

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
    @include('partials._theme')
</head>
<body class="d-flex flex-column login-page" @if($darkMode) data-bs-theme="dark" @endif>
<div class="row g-0 flex-fill">
    <div class="col-12 col-lg-6 col-xl-4 border-top-wide border-primary d-flex flex-column justify-content-center">
        <div class="container container-tight mt-auto px-lg-5">
            <h1 class="mb-4 text-center">
                <a href="{{ route('home') }}" class="navbar-brand navbar-brand-autodark">
                    @if($darkMode)
                        @if(App\Models\Setting::fetch('logo-light'))
                            <img src="@setting('logo-light')" alt="@setting('name', config('app.name'))">
                        @else
                            @setting('name', config('app.name'))
                       @endif
                    @else
                        @if(App\Models\Setting::fetch('logo-dark'))
                            <img src="@setting('logo-dark')" alt="@setting('name', config('app.name'))">
                        @else
                            @setting('name', config('app.name'))
                        @endif
                    @endif
                </a>
            </h1>
            @if (session('successMessage'))
                <div class="alert alert-success alert-important text-center" role="alert">
                    {{ session('successMessage') }}
                </div>
            @endif
            @if (session('errorMessage'))
                <div class="alert alert-danger alert-important text-center" role="alert">
                    {{ session('errorMessage') }}
                </div>
            @endif
            @if (session('infoMessage'))
                <div class="alert alert-info alert-important text-center" role="alert">
                    {{ session('infoMessage') }}
                </div>
            @endif
            @if (session('warningMessage'))
                <div class="alert alert-warning alert-important text-center" role="alert">
                    {{ session('warningMessage') }}
                </div>
            @endif
            @yield('content')
        </div>
        <footer class="footer footer-transparent mt-auto pt-3 pb-1">
            <div class="container-xl">
                <div class="row align-items-center flex-row-reverse">
                    <div class="col-5 text-end">
                        <ul class="list-unstyled mb-0">
                            @if(\App\Models\Setting::fetch('terms'))
                                <li>
                                    <a href="@setting('terms')" target="_blank" class="link-secondary">Terms and Conditions</a>
                                </li>
                           @endif
                           @if(\App\Models\Setting::fetch('privacypolicy'))
                                <li>
                                    <a href="@setting('privacypolicy')" target="_blank" class="link-secondary">Privacy Policy</a>
                                </li>
                            @endif
                        </ul>
                    </div>
                    <div class="col-7">
                        <ul class="list-inline list-inline-dots mb-0">
                            <li class="list-inline-item">
                                Copyright &copy; {{ date('Y') }}
                                <a href="{{ route('home') }}" class="link-secondary">@setting('name')</a>.<br/>
                                All rights reserved.
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </footer>
    </div>
    <div class="col-12 col-lg-6 col-xl-8 d-none d-lg-block">
        <div class="bg-cover h-100 min-vh-100"
             style="background-image: url('@setting('cover-image', Vite::asset('resources/img/cover.jpg'))');"></div>
    </div>
</div>
</body>
</html>
