@extends('layouts.application', [
    'title' => [
        'Parties',
        'Add'
    ]
]
)

@section('content')
    <div class="page-header">
        <h1>
            Parties
        </h1>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <form class="card" action="{{ route('parties.store') }}" method="post">
                {{ csrf_field() }}
                <div class="card-header">
                    <h3 class="card-title">Add Party</h3>
                </div>
                @include('parties._form')
                <div class="card-footer text-right">
                    <div class="d-flex">
                        <a class="btn btn-link" href="{{ route('parties.index') }}">Cancel</a>
                        <button class="btn btn-primary ml-auto" type="submit">Save</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
