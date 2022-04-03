@extends('layouts.application')

@section('content')
    <div class="page-header">
        <h1>{{ $party->name }}</h1>
    </div>

    <h2>Search</h2>

    <form action="{{ route('parties.upcoming.search', $party->code) }}" method="GET" class="form-inline mb-3">
        <input type="text" class="form-control" name="query" />
        <button type="submit" class="btn btn-primary">Search</button>
    </form>

    @if ($party->song)
        <h2>Now</h2>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover table-outline table-vcenter text-nowrap card-table">
                    <thead>
                    <tr>
                        <th>Name</th>
                        <th>Artists</th>
                        <th>Album</th>
                        <th>Length</th>
                    </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>{{ $party->song->name }}</td>
                            <td>{{ $party->song->artists->pluck('name')->join(', ') }}</td>
                            <td>{{ $party->song->album->name }}</td>
                            <td>{{ (new \Carbon\Carbon(0))->addMilliseconds($party->song->length)->format('i:s') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <h2>Next</h2>

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
                <tr>
                    <td>{{ $next->song->name }}</td>
                    <td>{{ $next->song->artists->pluck('name')->join(', ') }}</td>
                    <td>{{ $next->song->album->name }}</td>
                    <td>{{ (new \Carbon\Carbon(0))->addMilliseconds($next->song->length)->format('i:s') }}</td>
                    <td>{{ $next->votes }}</td>
                    <td>
                        @if ($next->hasVoted(Auth::user()))
                            <span class="text-muted">Voted</span>
                        @endif
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>

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
                @foreach($upcoming as $ucSong)
                    <tr>
                        <td>{{ $ucSong->song->name }}</td>
                        <td>{{ $ucSong->song->artists->pluck('name')->join(', ') }}</td>
                        <td>{{ $ucSong->song->album->name }}</td>
                        <td>{{ (new \Carbon\Carbon(0))->addMilliseconds($ucSong->song->length)->format('i:s') }}</td>
                        <td>{{ $ucSong->votes }}</td>
                        <td>
                            @if ($ucSong->hasVoted(Auth::user()))
                                <button class="btn btn-outline-primary" disabled>Vote</button>
                            @else
                                <form class="form-inline" method="post" action="{{ route('parties.upcoming.vote', [$party->code]) }}">
                                    {{ csrf_field() }}
                                    <input type="hidden" name="id" value="{{ $ucSong->song->spotify_id }}" />
                                    <button type="submit" class="btn btn-primary">Vote</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>


        @if ($upcoming->total() > $upcoming->count() )
            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        {{ $upcoming->links() }} }}
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
