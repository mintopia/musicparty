@extends('layouts.application')

@section('content')
    <div class="page-header">
        <h1>{{ $party->name }}</h1>
        @can('update', $party)
            <a class="btn ml-auto btn-outline-secondary" href="{{ route('parties.edit', $party) }}">
                <span class="fa fa-pencil"></span>
                Edit
            </a>
        @endcan
    </div>

    @if ($party->song)
        <h2>Playing Now</h2>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover table-outline table-vcenter text-nowrap card-table">
                    <thead>
                    <tr>
                        <th>Name</th>
                        <th>Artists</th>
                        <th>Album</th>
                        <th>Length</th>
                        <th>Started</th>
                    </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>{{ $party->song->name }}</td>
                            <td>{{ $party->song->artists->pluck('name')->join(', ') }}</td>
                            <td>{{ $party->song->album->name }}</td>
                            <td>{{ (new \Carbon\Carbon(0))->addMilliseconds($party->song->length)->format('i:s') }}</td>
                            <td>{{ $party->song_started_at->diffForHumans() }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <h2>Upcoming</h2>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover table-outline table-vcenter text-nowrap card-table">
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Artists</th>
                    <th>Album</th>
                    <th>Length</th>
                    <th>Votes</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @if ($next)
                    <tr>
                        <td>{{ $next->song->name }}</td>
                        <td>{{ $next->song->artists->pluck('name')->join(', ') }}</td>
                        <td>{{ $next->song->album->name }}</td>
                        <td>{{ (new \Carbon\Carbon(0))->addMilliseconds($next->song->length)->format('i:s') }}</td>
                        <td>{{ $next->votes }}</td>
                        <td></td>
                    </tr>
                @endif
                @foreach($upcoming as $ucSong)
                    <tr>
                        <td>{{ $ucSong->song->name }}</td>
                        <td>{{ $ucSong->song->artists->pluck('name')->join(', ') }}</td>
                        <td>{{ $ucSong->song->album->name }}</td>
                        <td>{{ (new \Carbon\Carbon(0))->addMilliseconds($ucSong->song->length)->format('i:s') }}</td>
                        <td>{{ $ucSong->votes }}</td>
                        <td></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
