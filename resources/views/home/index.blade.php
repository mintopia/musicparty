@extends('layouts.application')

@section('content')

    <div class="container">
        <h1 class="display-1 text-center mt-5 mb-5">
            <i class="bi bi-music-player"></i>
        </h1>

        <p class="lead text-center">
            <strong>
                Welcome to Music Party!
            </strong>
        </p>

        @auth
            <p>
                To join a party, enter the 4 digit code below.
            </p>
            <form method="post" action="{{ route('parties.join') }}">
                {{ csrf_field() }}
                <input type="text" class="form-element" name="code" id="code" />
                <button class="btn btn-primary ml-auto" type="submit">Submit</button>
            </form>

            @if (Auth::user()->can_create_party)
                @can('create', \App\Models\Party::class)
                    <p><a href="{{ route('parties.index') }}">Your Parties</a></p>
                @else
                    <p>You need to <a href="{{ route('auth.spotify_link') }}">connect with Spotify</a> to be able to create a party.</p>
                @endif
            @endif
        @else
            <p class="text-center mt-5 mb-5">
                <a href="{{ route('login') }}" class="bg-discord btn-lg btn">
                    <i class="bi bi-discord"></i>
                    Login with Discord
                </a>
            </p>

            <p class="lead text-center">
                Music Party is a colloborative party jukebox using Spotify.
            </p>
        @endauth
    </div>
@endsection
