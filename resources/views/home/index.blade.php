@extends('layouts.application')

@section('content')

    <div class="container">
        <div class="col-md-4 offset-md-4 mt-5 col-8 offset-2">
            <p class="lead text-center">
                <strong>
                    Welcome to Music Party!
                </strong>
            </p>

            <h1 class="display-1 text-center mt-5 mb-5">
                <i class="bi bi-music-note-list"></i>
            </h1>

            <p class="lead text-center mt-5">
                Music Party is a collaborative party jukebox using Spotify.
            </p>
            @auth
                <p class="mt-5">
                    Enter the 4 digit code for the party you want to join.
                </p>
                <form method="post" action="{{ route('parties.join') }}">
                    {{ csrf_field() }}
                    <div class="row">
                        <div class="input-group input-group-lg">
                            <input type="text" class="form-control" placeholder="Code" aria-label="Party Code" name="code" type="code" />
                            <button class="btn btn-primary" type="submit">Join</button>
                        </div>
                    </div>
                </form>

                @if (Auth::user()->party)
                    <p class="text-center mt-5">
                        <a href="{{ route('parties.show', Auth::user()->party->code) }}" class="btn-lg btn btn-primary">
                            <i class="bi bi-music-note-list"></i>
                            Continue your Party
                        </a>
                    </p>
                @elseif (Auth::user()->spotify->id)
                    <p class="text-center mt-5">
                        <a href="{{ route('parties.create') }}" class="btn-lg btn btn-primary">
                            <i class="bi bi-music-note-list"></i>
                            Create Party
                        </a>
                    </p>
                @elseif (Auth::user()->can_create_party)
                    <p class="mt-5">
                        To create a party, you need to connect your Spotify account.
                    </p>
                    <p class="text-center">
                        <a href="{{ route('auth.spotify_link') }}" class="bg-spotify btn-lg btn">
                            <i class="bi bi-spotify"></i>
                            Connect Spotify
                        </a>
                    </p>
                @endif
            @else
                <p class="text-center mt-5">
                    <a href="{{ route('login') }}" class="bg-discord btn-lg btn">
                        <i class="bi bi-discord"></i>
                        Login with Discord
                    </a>
                </p>
            @endauth
        </div>
    </div>
@endsection
