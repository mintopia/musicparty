@extends('layouts.app', [
    'activenav' => "party:{$party->code}"
])

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('parties.show', $party->code) }}">{{ $party->name }}</a>
    <li class="breadcrumb-item active"><a href="{{ route('parties.search', $party->code) }}">Search</a>
@endsection
@push('precontainer')
    @include('parties._player')
@endpush
@section('content')
    <div class="col-xl-8 offset-xl-2 col-lg-10 offset-lg-1">
        <h1>Search</h1>
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
        @if($results !== null)
            <h2 class="mt-5">Results</h2>

            @if ($results->count() === 0)
                <div class="empty">
                    <p class="text-secondary">
                        No results found
                    </p>
                </div>
            @else
                <div class="card">
                    <div class="list-group card-list-group">
                        @foreach($results as $item)
                            <search-result
                                party="{{ $party->code }}"
                                initialstate='@json($item)'
                                can_downvote="{{ $party->downvotes }}"
                            >
                            </search-result>
                        @endforeach
                    </div>
                    @include('partials._pagination', [
                        'page' => $results
                    ])
                </div>
            @endif
        @endif
    </div>
@endsection
