@extends('layouts.app', [
    'activenav' => "party:{$party->code}"
])

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('parties.show', $party->code) }}">{{ $party->name }}</a>
    <li class="breadcrumb-item"><a href="{{ route('parties.users.index', $party->code) }}">Users</a>
    <li class="breadcrumb-item active"><a href="{{ route('parties.users.show', [$party->code, $member->id]) }}">{{ $member->user->nickname }}</a>
@endsection
@push('precontainer')
    @include('parties._player')
@endpush
@section('content')
    <div class="page-header mt-0">
        <h1>{{ $member->user->nickname }}</h1>
    </div>

    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="datagrid">
                        <div class="datagrid-item">
                            <div class="datagrid-title">Nickname</div>
                            <div class="datagrid-content">{{ $member->user->nickname }}</div>
                        </div>
                        <div class="datagrid-item">
                            <div class="datagrid-title">Role</div>
                            <div class="datagrid-content">{{ $member->role->name }}</div>
                        </div>
                        <div class="datagrid-item">
                            <div class="datagrid-title">Joined Party</div>
                            <div class="datagrid-content">
                                <span title="{{ $member->created_at->format('Y-m-d H:i:s') }}">
                                    {{ $member->created_at->diffForHumans() }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer align-content-end d-flex btn-list">
                    <a href="{{ route('parties.users.edit', [$party->code, $member->id]) }}" class=" ms-auto btn btn-primary">
                        <i class="icon ti ti-edit"></i>
                        Edit
                    </a>
                </div>
            </div>
        </div>
    </div>

    <h2>Votes</h2>

    <div class="row">
        <div class="col-md-12 col-lg-3 mb-4">
            <form action="{{ route('parties.users.show', [$party->code, $member->id]) }}" method="get" class="card">
                <div class="card-header">
                    <h3 class="card-title">Search</h3>
                </div>
                <div class="card-body">
                    @include('partials._searchtextfield', [
                        'name' => 'Song Name',
                        'property' => 'name',
                    ])

                    @include('partials._searchselectfield', [
                        'name' => 'Type',
                        'property' => 'type',
                        'valueProperty' => 'code',
                        'nameProperty' => 'name',
                        'options' => $types,
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
                            <th>Song</th>
                            <th class="text-center">Vote</th>
                            <th>Time</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($votes as $vote)
                            <tr>
                                <td>
                                    <div class="d-flex">
                                        <div class="col-auto">
                                            <img src="{{ $vote->upcomingSong->song->album->image_url }}" height="40" width="40" title="{{ $vote->upcomingSong->song->album->name }}"/>
                                        </div>
                                        <div class="col ps-2">
                                            <a href="{{ route('parties.songs.show', [$party->code, $vote->upcomingSong->id]) }}">
                                                {{ $vote->upcomingSong->song->name }}
                                            </a>
                                            <div class="text-secondary text-wrap">
                                                {{ $vote->upcomingSong->song->artists->pluck('name')->implode(', ') }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    @if ($vote->value > 0)
                                        <i class="icon ti ti-arrow-big-up-filled text-success"></i>
                                    @else
                                        <i class="icon ti ti-arrow-big-down-filled text-danger"></i>
                                    @endif
                                </td>
                                <td>
                                    <span title="{{ $vote->created_at->format('Y-m-d H:i:s') }}">
                                        {{ $vote->created_at->diffForHumans() }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @include('partials._pagination', [
                    'page' => $votes
                ])
            </div>
        </div>
    </div>
@endsection
