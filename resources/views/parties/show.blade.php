@extends('layouts.application')

@section('content')

    <div class="container-fluid">
        @include('parties._player')

        <div class="row">
            <div class="col-10 offset-1 mt-5 mb-5">
                <form action="{{ route('parties.upcoming.search', $party->code) }}" method="GET">
                    <div class="input-group input-group-lg">
                        <input type="text" class="form-control form-control" name="query" placeholder="Search" autocomplete="off" />
                        <button type="submit" class="btn btn-primary">Search</button>
                    </div>
                </form>
            </div>
        </div>

        <upcoming party="{{ $party->code }}" can_delete="{{ $party->isAdmin(Auth::user()) ? true : false }}" />
    </div>
@endsection
