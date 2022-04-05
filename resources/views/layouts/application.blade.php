<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <link href="/css/app.css" rel="stylesheet">

        <title>
            Music Party
            @if (isset($title) && is_array($title))
                - {{ implode(' - ', $title) }}
            @elseif (isset($title))
                - {{ $title }}
            @endif
        </title>
    </head>
    <body class="bg-dark cover-bg">
        <nav class="navbar-expand-md navbar navbar-dark bg-primary">
            <div class="container-fluid">
                <a class="navbar-brand" href="{{ route('home') }}">
                    <i class="bi bi-music-player-fill"></i>
                    Music Party
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav ms-auto d-flex">
                        @auth
                            <li>
                                <a href="javascript:void();" class="nav-link pr-0 leading-none" data-toggle="dropdown">
                                    <span class="avatar rounded" style="background-image: url('{{ Auth::user()->avatar }}');"></span>
                                    <span class="ml-2 d-none d-lg-block">
                                                <span class="text-default">{{ Auth::user()->nickname }}</span>
                                                <small class="text-muted d-block mt-1">{{ Auth::user()->discord_name }}</small>
                                            </span>
                                </a>
                                <div class="dropdown-menu dropdown-menu-right dropdown-menu-arrow">
                                    <a class="dropdown-item" href="{{ route('logout') }}">
                                        <i class="dropdown-icon fe fe-log-out"></i> Logout
                                    </a>
                                </div>
                        </li>
                        @else
                            <li class="nav-item">
                                <a class="nav-link" aria-current="page" href="{{ route('login') }}">
                                    <i class="bi bi-discord"></i>
                                    Login with Discord
                                </a>
                            </li>
                        @endauth
                    </ul>
                </div>
            </div>
        </nav>
        @if ($message = Session::get('successMessage'))
            <div class="alert alert-success alert-block">
                {{ $message }}
            </div>

        @endif

        @if ($message = Session::get('errorMessage'))
            <div class="alert alert-danger alert-block">
                {{ $message }}
            </div>
        @endif

        @yield('content')

        <footer class="navbar fixed-bottom navbar-dark bg-dark">
            <div class="container-fluid">
                <div class="d-flex flex-fill">
                    <div class="flex-grow-1">
                        <small>
                            <span class="navbar-text">Copyright &copy; {{ date('Y') }}</span>
                            <a class="link-primary" href="https://github.com/mintopia">Mintopia</a>
                        </small>
                    </div>
                    <div class="text-end">
                        <a href="https://github.com/mintopia/musicparty" class="btn btn-outline-primary btn-sm">Source</a>
                    </div>
                </div>
            </div>
        </footer>


        <script src="/js/app.js"></script>
        @yield('footer')
    </body>
</html>
