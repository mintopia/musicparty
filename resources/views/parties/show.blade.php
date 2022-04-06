@extends('layouts.application')

@section('content')

    <div class="container-fluid">
        <div class="row">
            {{ $party->name }}
        </div>

        @if ($party->song)
            <div class="row">
                <img src="{{ $party->song->album->image_url }}" title="{{ $party->song->album->name }}" class="float-start me-3" style="height: 40px; width: 40px;" />
                <span data-field="title">{{ $party->song->name }}</span>
                <span data-field="artists">{{ $party->song->artists->pluck('name')->join(', ') }}</span>


                <i class="bi bi-pause"></i>
                <i class="bi bi-skip-end"></i>
            </div>
        @endif

        @if ($next)
            <div class="row">
                <img src="{{ $next->song->album->image_url }}" title="{{ $next->song->album->name }}" class="float-start me-3" style="height: 40px; width: 40px;" />
                <span data-field="title">{{ $next->song->name }}</span>
                <br />
                <small>
                    <span data-field="artists">{{ $next->song->artists->pluck('name')->join(', ') }}</span>
                    &middot;
                    <span data-field="votes">
                                @if ($next->votes == 0)
                            Fallback Track
                        @else
                            {{ $next->votes }} vote{{ $next->votes != 1 ? 's' : '' }}
                        @endif
                    </span>
                </small>
            </div>
        @endif

        <div class="row">
            <div class="col-10 offset-1 mt-5 mb-5">
                <form action="{{ route('parties.upcoming.search', $party->code) }}" method="GET">
                    <div class="input-group input-group-lg">
                        <input type="text" class="form-control form-control" name="query" placeholder="Search" />
                        <button type="submit" class="btn btn-primary">Search</button>
                    </div>
                </form>
            </div>
        </div>

        @if ($upcoming->total() > 0)
            <div class="row">
                <table class="table table-dark table-striped align-middle table-responsive">
                    @foreach ($upcoming as $ucSong)
                        <tr class="pt-2 pb-2">
                            <td>
                                <img data-field="album" src="{{ $ucSong->song->album->image_url }}" title="{{ $ucSong->song->album->name}}" class="img-fluid party-album float-start me-3" />
                                <span data-field="title">{{ $ucSong->song->name }}</span>
                                <br />
                                <small>
                                    <span data-field="artists" class="text-truncate" style="max-width: 70%;">{{ $ucSong->song->artists->pluck('name')->join(', ') }}</span>
                                    &middot;
                                    <span data-field="votes">
                                        @if ($ucSong->votes == 0)
                                            Fallback Track
                                        @else
                                            {{ $ucSong->votes }} vote{{ $ucSong->votes != 1 ? 's' : '' }}
                                        @endif
                                    </span>
                                </small>
                            </td>
                            <td class="text-end fs-4 fw-bold pe-2" data-field="actions">
                                @if (Auth::user() == $party->user)
                                    <a href="{{ route('parties.upcoming.delete', [$party->code, $ucSong->id]) }}" class="link-light"><i class="bi bi-x-lg mr-2"></i></a>
                                @endif
                                @if ($ucSong->hasVoted(Auth::user()))
                                    <i class="bi bi-heart-fill"></i>
                                @else
                                    <a href="{{ route('parties.upcoming.vote', [$party->code, $ucSong->song->spotify_id]) }}" class="link-light"> <i class="bi bi-heart"></i></a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif
    </div>
@endsection
