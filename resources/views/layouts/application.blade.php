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
        <div id="app">
            <nav class="navbar navbar-dark bg-primary navbar-expand-sm shadow-lg">
                <div class="container-fluid">
                    <a class="navbar-brand" href="{{ route('home') }}">
                        <i class="bi bi-music-note-list"></i>
                        Music Party
                    </a>
                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    <ul class="navbar-nav ms-auto d-none d-sm-flex">
                        @auth
                            <li class="nav-item dropdown">
                                <a class="nav-link d-flex flex-fill active" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <div class="flex-grow-1 text-end pe-2 user-menu-text">
                                        <strong>{{ Auth::user()->nickname }}</strong>
                                        <small class="d-block">{{ Auth::user()->discord_name }}</small>
                                    </div>
                                    <div>
                                        <img src="{{ Auth::user()->avatar }}" class="rounded user-menu-avatar" />
                                    </div>
                                </a>

                                <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark" aria-labelledby="userDropdown">
                                    <li><a class="dropdown-item" href="{{ route('logout') }}">Logout</a></li>
                                </ul>
                            </li>
                        @else
                            <li class="nav-item">
                                <a class="nav-link active" aria-current="page" href="{{ route('login') }}">
                                    <i class="bi bi-discord"></i>
                                    Login with Discord
                                </a>
                            </li>
                        @endauth
                    </ul>
                </div>
            </nav>

            <div class="collapse bg-dark border-bottom border-primary d-sm-none" id="navbarContent">
                @auth
                    <div class="d-flex p-2 bg-primary border-top border-dark text-light">
                        <div>
                            <img src="{{ Auth::user()->avatar }}" class="rounded mt-1" style="height: 2.5em;" />
                        </div>
                        <div class="ms-2">
                            <strong>{{ Auth::user()->nickname }}</strong>
                            <small class="d-block">{{ Auth::user()->discord_name }}</small>
                        </div>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link link-light pt-3 pb-3" href="{{ route('logout') }}">
                                <i class="bi bi-door-open"></i>
                                Logout
                            </a>
                        </li>
                    </ul>
                @else
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link link-light pt-3 pb-3" href="{{ route('login') }}">
                                <i class="bi bi-discord"></i>
                                Login with Discord
                            </a>
                        </li>
                    </ul>
                @endauth
            </div>
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

            <footer class="navbar fixed-bottom navbar-dark bg-dark position-absolute bottom-0 w-100">
                <div class="container-fluid">
                    <div class="d-flex flex-fill">
                        <div class="flex-grow-1">
                            <small>
                                <span class="navbar-text">Copyright &copy; {{ date('Y') }}</span>
                                <a class="link-primary ms-1" href="https://github.com/mintopia">Mintopia</a>
                            </small>
                        </div>
                        <div class="text-end">
                            <a href="https://github.com/mintopia/musicparty" class="btn btn-outline-primary btn-sm">Source</a>
                        </div>
                    </div>
                </div>
            </footer>


        </div>
        <script src="/js/app.js"></script>
        @yield('footer')
    </body>
</html>
