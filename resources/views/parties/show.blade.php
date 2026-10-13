@extends('layouts.app', [
    'activenav' => "party:{$party->code}"
])

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
    <li class="breadcrumb-item active"><a href="{{ route('parties.show', $party->code) }}">{{ $party->name }}</a>
@endsection
@push('precontainer')
    @include('parties._player')
@endpush
@section('content')

    @if ($spotifyNeedsRelink)
        <div class="col-xl-8 offset-xl-2 col-lg-10 offset-lg-1">
            <div class="alert alert-warning" role="alert" id="spotify-relink-banner">
                Spotify needs re-linking. <a href="{{ route('linkedaccounts.create', 'spotify') }}">Re-link your Spotify account</a> to resume playback updates.
            </div>
        </div>
    @endif

    <div class="col-xl-8 offset-xl-2 col-lg-10 offset-lg-1">
        @if ($canManage)
            <div class="text-secondary small mb-1" data-testid="current-player">
                Player: {{ $party->playback_source ? Str::headline($party->playback_source->value) : 'None' }}
            </div>
        @endif
        <next
            code="{{ $party->code }}"
            can_manage="{{ $canManage }}"
            initialstate='@json($party->getState())'>
        </next>
        <div class="d-flex flex-fill align-text-bottom mb-1">
            <div class="flex-grow-1">
                <h3 class="mt-2">{{ $party->weighted ? 'Upcoming Songs' : 'Queue' }}</h3>
            </div>
            <div class="text-end">
                <form action="{{ route('parties.search', $party->code) }}" method="get">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <div class="input-icon">
                                <input type="text" class="form-control" name="query" id="query" value="{{ old('query', $params->query ?? '') }}" placeholder="Search…">
                                <span class="input-icon-addon">
                                  <i class="icon ti ti-music-search"></i>
                                </span>
                            </div>
                        </div>
                        <div class="flex-column ps-2">
                            <button type="submit" class="btn btn-primary">Search</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        @if($party->weighted)
        <div class="d-flex flex-fill align-text-bottom mt-2 mb-1">
            <p>Tracks with more upvotes are more likely to be played.</p>
        </div>
        @endif

        <upcoming
            party="{{ $party->code }}"
            can_manage="{{ $canManage }}"
            can_downvote="{{ $party->downvotes }}"
            initialstate='@json($upcoming)'
        ></upcoming>
    </div>
@endsection
@push('footer')
    <script>
        // Keepalive, to ensure our session doesn't expire
        setInterval(function() {
            fetch('/api/v1/ping');
        }, 60000);
    </script>
@endpush
