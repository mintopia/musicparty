@extends('layouts.app', [
    'activenav' => 'profile',
])

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
    <li class="breadcrumb-item active"><a href="{{ route('user.profile') }}">Link Spotify Account</a></li>
@endsection

@section('content')
    <div class="page-header mt-2">
        <h1>Link Spotify Account</h1>
    </div>

    <p>
        For Music Party to work, we need to link your Spotify account twice. The first link is for controlling
        the party and the second link is for guests to search for music.
    </p>

    <div class="row row-deck">
        <div class="col-md-3 offset-md-3">
            <div class="card">
                <div class="card-body">
                    @if($spotify)
                        <p class="text-center">
                            <i class="icon ti ti-music-bolt icon-lg text-success m-6"></i>
                        </p>
                        <div class="mt-2 text-center">{{ $spotify->name }}</div>
                    @else
                        <p class="text-center">
                            <i class="icon ti ti-music-bolt icon-lg text-muted m-6"></i>
                        </p>
                        <div class="mt-2">
                            <a class="btn btn-success w-100"
                               href="{{ route('linkedaccounts.create', 'spotify') }}">
                                <i class="icon ti ti-brand-spotify"></i>
                                Link with Spotify
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    @if($search)
                        <p class="text-center">
                            <i class="icon ti ti-music-search icon-lg text-success m-6"></i>
                        </p>
                        <div class="mt-2 text-center">{{ $search->name }}</div>
                    @else
                        <p class="text-center">
                            <i class="icon ti ti-music-search icon-lg text-muted m-6"></i>
                        </p>
                        <div class="mt-2">
                            <a class="btn btn-success w-100"
                               href="{{ route('linkedaccounts.create', 'spotifysearch') }}">
                                <i class="icon ti ti-brand-spotify"></i>
                                Link with Spotify (Search)
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6 offset-md-3 mt-2">
            @if ($spotify && $search)
                <a href="{{ route('parties.create') }}" class="btn btn-primary w-100">
                    <i class="icon ti ti-playlist-add"></i>
                    Create Party
                </a>
            @else
                <button class="btn btn-primary w-100" disabled>
                    <i class="icon ti ti-playlist-add"></i>
                    Create Party
                </button>
            @endif
        </div>
    </div>
@endsection
