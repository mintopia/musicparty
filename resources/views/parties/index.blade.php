@extends('layouts.application')

@section('content')
    <div class="page-header">
        <h1>Games</h1>
        @can('create', \App\Models\Party::class)
            <a class="btn ml-auto btn-outline-success" href="{{ route('parties.create') }}">
                <span class="fa fa-plus"></span>
                Add Party
            </a>
        @endcan
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover table-outline table-vcenter text-nowrap card-table">
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Code</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach($parties as $party)
                    <tr>
                        <td>
                            <a href="{{ route('parties.show', $party->code) }}">
                                {{ $party->name }}
                            </a>
                        </td>
                        <td>
                            {{ $party->code }}
                        </td>
                        <td></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @include('partials._pagination', [
            'page' => $parties
        ])
    </div>
@endsection
