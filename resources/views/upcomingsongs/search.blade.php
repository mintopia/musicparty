@extends('layouts.application')

@section('content')

    <div class="container-fluid">
        <div class="row">
            {{ $party->name }}
        </div>

        <div class="row">
            <div class="col-10 offset-1 mt-5 mb-5">
                <form action="{{ route('parties.upcoming.search', $party->code) }}" method="GET">
                    <div class="input-group input-group-lg">
                        <input type="text" class="form-control form-control" name="query" placeholder="Search" value="{{ $query }}" />
                        <button type="submit" class="btn btn-primary">Search</button>
                    </div>
                </form>
            </div>
        </div>

        @if ($tracks->total() > 0)
            <div class="row">
                <table class="table table-dark table-striped align-middle table-responsive">
                    @foreach ($tracks as $track)
                        <tr class="pt-2 pb-2">
                            <td>
                                <img data-field="album" src="{{ $track->album->images[0]->url }}" title="{{ $track->album->name}}" class="img-fluid party-album float-start me-3" />
                                <span data-field="title">{{ $track->name }}</span>
                                <br />
                                <small>
                                    <span data-field="artists" class="text-truncate" style="max-width: 70%;">{{ collect($track->artists)->pluck('name')->join(', ') }}</span>
                                    &middot;
                                    <span data-field="votes">
                                        @if ($track->votes == 0)
                                            Fallback Track
                                        @else
                                            {{ $track->votes }} vote{{ $track->votes != 1 ? 's' : '' }}
                                        @endif
                                    </span>
                                </small>
                            </td>
                            <td class="text-end fs-4 fw-bold pe-2" data-field="actions">
                                @if ($track->hasVoted)
                                    <i class="bi bi-heart-fill"></i>
                                @else
                                    <a href="{{ route('parties.upcoming.vote', [$party->code, $track->id]) }}" class="link-light"> <i class="bi bi-heart"></i></a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif
    </div>
@endsection
