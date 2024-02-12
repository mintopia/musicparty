@extends('layouts.app', [
    'activenav' => "party:{$party->code}"
])

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('parties.show', $party->code) }}">{{ $party->name }}</a>
    <li class="breadcrumb-item active"><a href="{{ route('parties.songs.index', $party->code) }}">Songs</a>
@endsection
@push('precontainer')
    @include('parties._player')
@endpush
@section('content')
    <div class="page-header mt-0">
        <h1>Songs</h1>
    </div>

    <div class="row">
        <div class="col-md-12 col-lg-3 mb-4">
            <form action="{{ route('parties.songs.index', $party->code) }}" method="get" class="card">
                <div class="card-header">
                    <h3 class="card-title">Search</h3>
                </div>
                <div class="card-body">
                    @include('partials._searchtextfield', [
                        'name' => 'Name',
                        'property' => 'name',
                    ])

                    @include('partials._searchtextfield', [
                        'name' => 'Artist',
                        'property' => 'artist',
                    ])

                    @include('partials._searchtextfield', [
                        'name' => 'Album',
                        'property' => 'album',
                    ])

                    @include('partials._searchselectfield', [
                        'name' => 'Type',
                        'property' => 'type',
                        'valueProperty' => 'code',
                        'nameProperty' => 'name',
                        'options' => $types,
                    ])

                </div>
                <div class="card-footer d-flex">
                    <button class="btn btn-primary ms-auto" type="submit">Search</button>
                </div>
            </form>
        </div>

        <div class="col-lg-9 col-md-12">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover table-outline table-vcenter text-nowrap card-table">
                        <thead>
                        <tr>
                            <th>Song</th>
                            <th class="text-center">
                                @include('partials._sortheader', [
                                    'title' => 'Votes',
                                    'field' => 'votes',
                                ])
                            </th>
                            <th class="text-center">
                                @include('partials._sortheader', [
                                    'title' => 'Score',
                                    'field' => 'score',
                                ])
                            </th>
                            <th>
                                @include('partials._sortheader', [
                                    'title' => 'Requested',
                                    'field' => 'created_at',
                                ])
                            </th>
                            <th>
                                @include('partials._sortheader', [
                                    'title' => 'Sent to Spotify',
                                    'field' => 'queued_at',
                                ])
                            </th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($songs as $song)
                            <tr>
                                <td>
                                    <div class="d-flex">
                                        <div class="col-auto">
                                            <img src="{{ $song->song->album->image_url }}" height="40" width="40" title="{{ $song->song->album->name }}"/>
                                        </div>
                                        <div class="col ps-2">
                                            <a href="{{ route('parties.songs.show', [$party->code, $song->id]) }}">
                                                {{ $song->song->name }}
                                            </a>
                                            <div class="text-secondary text-wrap">
                                                {{ $song->song->artists->pluck('name')->implode(', ') }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">{{ $song->votes_count }}</td>
                                <td class="text-center">{{ $song->score }}</td>
                                <td>
                                    <span title="{{ $song->created_at->format('Y-m-d H:i:s') }}">
                                        {{ $song->created_at->diffForHumans() }}
                                    </span>
                                </td>
                                <td>
                                    @if ($song->queued_at)
                                        <span title="{{ $song->queued_at->format('Y-m-d H:i:s') }}">
                                            {{ $song->queued_at->diffForHumans() }}
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
                @include('partials._pagination', [
                    'page' => $songs
                ])
            </div>
        </div>
    </div>
@endsection
