@extends('layouts.app', [
    'activenav' => "party:{$party->code}"
])

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('parties.show', $party->code) }}">{{ $party->name }}</a>
    <li class="breadcrumb-item active"><a href="{{ route('parties.users.index', $party->code) }}">Users</a>
@endsection
@push('precontainer')
    @include('parties._player')
@endpush
@section('content')
    <div class="page-header mt-0">
        <h1>Users</h1>
    </div>

    <div class="row">
        <div class="col-md-12 col-lg-3 mb-4">
            <form action="{{ route('parties.users.index', $party->code) }}" method="get" class="card">
                <div class="card-header">
                    <h3 class="card-title">Search</h3>
                </div>
                <div class="card-body">
                    @include('partials._searchtextfield', [
                        'name' => 'Nickname',
                        'property' => 'nickname',
                    ])

                    @include('partials._searchselectfield', [
                        'name' => 'Role',
                        'property' => 'role',
                        'valueProperty' => 'code',
                        'nameProperty' => 'name',
                        'options' => $roles,
                    ])

                </div>
                <div class="card-footer d-flex">
                    <button class="btn btn-primary ms-auto" type="submit">Search</button>
                </div>
            </form>
        </div>

        <div class="col-lg-9 col-md-12">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover table-outline table-vcenter text-nowrap card-table">
                        <thead>
                        <tr>
                            <th>
                                @include('partials._sortheader', [
                                    'title' => 'Nickname',
                                    'field' => 'nickname',
                                ])
                            </th>
                            <th>
                                @include('partials._sortheader', [
                                    'title' => 'Role',
                                    'field' => 'role',
                                ])
                            </th>
                            <th>
                                @include('partials._sortheader', [
                                    'title' => 'Joined',
                                    'field' => 'created_at',
                                    'direction' => 'desc',
                                ])
                            </th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($members as $member)
                            <tr>
                                <td>
                                    <a href="{{ route('parties.users.show', [$party->code, $member->id]) }}">
                                        {{ $member->user->nickname }}
                                    </a>
                                </td>
                                <td>
                                    {{ $member->role->name }}
                                </td>
                                <td>
                                    <span title="{{ $member->created_at->format('Y-m-d H:i:s') }}">
                                        {{ $member->created_at->diffForHumans() }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @include('partials._pagination', [
                    'page' => $members
                ])
            </div>
        </div>
    </div>
@endsection
