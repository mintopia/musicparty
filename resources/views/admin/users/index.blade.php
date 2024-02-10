@extends('layouts.app', [
    'activenav' => 'admin',
])

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
    <li class="breadcrumb-item active"><a href="{{ route('admin.users.index') }}">Users</a></li>
@endsection

@section('content')
    <div class="page-header mt-0">
        <h1>Users</h1>
    </div>

    <div class="row">
        <div class="col-md-12 col-lg-3 mb-4">
            <form action="{{ route('admin.users.index') }}" method="get" class="card">
                <div class="card-header">
                    <h3 class="card-title">Search</h3>
                </div>
                <div class="card-body">
                    @include('partials._searchtextfield', [
                        'name' => 'ID',
                        'property' => 'id',
                    ])
                    @include('partials._searchtextfield', [
                        'name' => 'Nickname',
                        'property' => 'nickname',
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
                                    'title' => 'ID',
                                    'field' => 'id',
                                ])
                            </th>
                            <th>
                                @include('partials._sortheader', [
                                    'title' => 'Nickname',
                                    'field' => 'nickname',
                                ])
                            </th>
                            <th>
                                @include('partials._sortheader', [
                                    'title' => 'Last Login',
                                    'field' => 'last_login',
                                    'direction' => 'desc',
                                ])
                            </th>
                            <th>
                                @include('partials._sortheader', [
                                    'title' => 'Created',
                                    'field' => 'created_at',
                                    'direction' => 'desc',
                                ])
                            </th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($users as $user)
                            <tr>
                                <td class="text-muted">{{ $user->id }}</td>
                                <td>
                                    <a href="{{ route('admin.users.show', $user->id) }}">
                                        {{ $user->nickname }}
                                    </a>
                                </td>
                                <td>
                                    @if($user->last_login)
                                        <span title="{{ $user->last_login->format('Y-m-d H:i:s') }}">
                                                {{ $user->last_login->diffForHumans() }}
                                            </span>
                                    @else
                                        <span class="text-muted">Never</span>
                                    @endif
                                </td>
                                <td>
                                        <span title="{{ $user->created_at->format('Y-m-d H:i:s') }}">
                                            {{ $user->created_at->diffForHumans() }}
                                        </span>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @include('partials._pagination', [
                    'page' => $users
                ])
            </div>
        </div>
    </div>
@endsection
