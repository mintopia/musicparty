@extends('layouts.application')

@section('content')
    <p>Welcome to the very basic website for Music Party. Sorry it's not prettier.</p>

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
    @endauth
    @guest
        <p>
            To join a party or vote, you will need to <a href="{{ route('login') }}">login with Discord</a>.
        </p>
    @endguest
@endsection
