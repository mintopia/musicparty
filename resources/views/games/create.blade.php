@extends('layouts.application', [
    'title' => [
        'Games',
        'Add'
    ]
]
)

@section('content')
    <div class="page-header">
        <h1>
            Games
        </h1>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <form class="card" action="{{ route('games.store') }}" method="post">
                {{ csrf_field() }}
                <div class="card-header">
                    <h3 class="card-title">Add Game</h3>
                </div>
                @include('games._form')
                <div class="card-footer text-right">
                    <div class="d-flex">
                        <a class="btn btn-link" href="{{ route('games.index') }}">Cancel</a>
                        <button class="btn btn-primary ml-auto" type="submit">Save</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
