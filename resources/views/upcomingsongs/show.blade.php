@extends('layouts.app', [
    'activenav' => "party:{$party->code}"
])

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('parties.show', $party->code) }}">{{ $party->name }}</a>
    <li class="breadcrumb-item"><a href="{{ route('parties.songs.index', $party->code) }}">Songs</a>
    <li class="breadcrumb-item active"><a href="{{ route('parties.songs.show', [$party->code, $song->id]) }}">{{ $song->song->name }}</a>
@endsection
@push('precontainer')
    @include('parties._player')
@endpush
@section('content')
    <div class="page-header mt-0">
        <h1>{{ $song->song->name }}</h1>
    </div>

    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-lg-2 d-lg-block d-none">
                            <img src="{{ $song->song->album->image_url }}" class="rounded" title="{{ $song->song->album->name }}" />
                        </div>
                        <div class="col-lg-10">
                            <div class="datagrid">
                                <div class="datagrid-item">
                                    <div class="datagrid-title">Artists</div>
                                    <div class="datagrid-content">
                                        @foreach($song->song->artists as $artist)
                                            <a href="{{ route('parties.songs.index', [$party->code, 'artist' => $artist->name]) }}">{{ $artist->name }}</a>@if($artist->id != $song->song->artists->last()->id), @endif
                                        @endforeach
                                    </div>
                                </div>
                                <div class="datagrid-item">
                                    <div class="datagrid-title">Album</div>
                                    <div class="datagrid-content">
                                        <a href="{{ route('parties.songs.index', [$party->code, 'album' => $song->song->album->name]) }}">{{ $song->song->album->name }}</a>
                                    </div>
                                </div>
                                <div class="datagrid-item">
                                    <div class="datagrid-title">Duration</div>
                                    <div class="datagrid-content">{{ format_ms($song->song->length) }}</div>
                                </div>
                                <div class="datagrid-item">
                                    <div class="datagrid-title">Votes</div>
                                    <div class="datagrid-content">{{ $song->votes()->count() }}</div>
                                </div>
                                <div class="datagrid-item">
                                    <div class="datagrid-title">Score</div>
                                    <div class="datagrid-content">{{ $song->score }}</div>
                                </div>
                                <div class="datagrid-item">
                                    <div class="datagrid-title">Requested</div>
                                    <div class="datagrid-content">
                                        <span title="{{ $song->created_at->format('Y-m-d H:i:s') }}">
                                            {{ $song->created_at->diffForHumans() }}
                                        </span>
                                    </div>
                                </div>
                                <div class="datagrid-item">
                                    <div class="datagrid-title">Requested By</div>
                                    <div class="datagrid-content">
                                        @if($song->user)
                                            <a href="{{ route('parties.users.show', [$party->code, $song->user->getPartyMember($party)]) }}">{{ $song->user->nickname }}</a>
                                        @else
                                            Fallback Track
                                        @endif
                                    </div>
                                </div>
                                <div class="datagrid-item">
                                    <div class="datagrid-title">Sent to Spotify</div>
                                    <div class="datagrid-content">
                                        @if($song->queued_at)
                                            <span title="{{ $song->queued_at->format('Y-m-d H:i:s') }}">
                                                {{ $song->queued_at->diffForHumans() }}
                                            </span>
                                        @else
                                            <span class="text-mute">Not Sent</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer align-content-end d-flex btn-list">
                    <a href="https://open.spotify.com/track/{{ $song->song->spotify_id }}" class="ms-auto btn btn-green text-white" target="_blank">
                        <i class="icon ti ti-brand-spotify"></i>
                        View on Spotify
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <h2>Votes</h2>
            @if($votes->count() > 0)
                <div class="card">
                    <div class="table-responsive">
                        <table class="table table-hover table-outline table-vcenter text-nowrap card-table">
                            <thead>
                            <tr>
                                <th>User</th>
                                <th class="text-center">Vote</th>
                                <th>Time</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($votes as $vote)
                                <tr>
                                    <td>
                                        <a href="{{ route('parties.users.show', [$party->code, $vote->user->getPartyMember($party)]) }}">{{ $vote->user->nickname }}</a>
                                    </td>
                                    <td class="text-center">
                                        @if ($vote->value > 0)
                                            <i class="icon ti ti-arrow-big-up-filled text-success"></i>
                                        @else
                                            <i class="icon ti ti-arrow-big-down-filled text-danger"></i>
                                        @endif
                                    </td>
                                    <td>
                                        <span title="{{ $vote->created_at->format('Y-m-d H:i:s') }}">
                                            {{ $vote->created_at->diffForHumans() }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    @include('partials._pagination', [
                        'page' => $votes
                    ])
                </div>
            @else
                <div class="empty">
                    <p>There have been no votes</p>
                </div>
            @endif
        </div>
        <div class="col-md-6">
            <h2>Other Appearances</h2>

            @if($other->count() > 0)
                <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover table-outline table-vcenter text-nowrap card-table">
                        <thead>
                        <tr>
                            <th>Requested</th>
                            <th class="text-center">Votes</th>
                            <th class="text-center">Score</th>
                            <th>Sent to Spotify</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($other as $otherSong)
                            <tr>
                                <td>
                                    <a href="{{ route('parties.songs.show', [$party->code, $otherSong->id]) }}" title="{{ $otherSong->created_at->format('Y-m-d H:i:s') }}">{{ $otherSong->created_at->diffForHumans() }}</a>
                                </td>
                                <td class="text-center">{{ $otherSong->votes_count }}</td>
                                <td class="text-center">{{ $otherSong->score }}</td>
                                <td>
                                    @if ($otherSong->queued_at)
                                        <span title="{{ $otherSong->queued_at->format('Y-m-d H:i:s') }}">
                                            {{ $otherSong->queued_at->diffForHumans() }}
                                        </span>
                                    @else
                                        <span class="text-muted">Not Sent to Spotify</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @else
                <div class="empty">
                    <p>This song has no other appearances</p>
                </div>
            @endif
        </div>
    </div>
@endsection
