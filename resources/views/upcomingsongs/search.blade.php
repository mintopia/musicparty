@extends('layouts.application')

@section('content')
    <div class="page-header">
        <h1>{{ $party->name }}</h1>
    </div>

    <h2>Search</h2>

    <form action="{{ route('parties.upcoming.search', $party->code) }}" method="GET" class="form-inline mb-3">
        <input type="text" class="form-control" value="{{ $query }}" name="query" />
        <button type="submit" class="btn btn-primary">Search</button>
    </form>

    <h2>Results</h2>

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
                @foreach($tracks as $track)
                    <tr>
                        <td>{{ $track->name }}</td>
                        <td>
                            {{ collect($track->artists)->pluck('name')->join(', ') }}
                        </td>
                        <td>{{ $track->album->name }}</td>
                        <td>{{ (new \Carbon\Carbon(0))->addMilliseconds($track->duration_ms)->format('i:s') }}</td>
                        <td>{{ $track->votes }}</td>
                        <td>
                            @if ($track->hasVoted)
                                <button class="btn btn-outline-primary" disabled>Vote</button>
                            @else
                                <form class="form-inline" method="post" action="{{ route('parties.upcoming.vote', [$party->code]) }}">
                                    {{ csrf_field() }}
                                    <input type="hidden" name="id" value="{{ $track->id }}" />
                                    <button type="submit" class="btn btn-primary">Vote</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>


        @if ($tracks->total() > $tracks->count() )
            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        {{ $tracks->links() }} }}
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
