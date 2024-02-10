@extends('layouts.app', [
    'activenav' => 'admin',
])

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
    <li class="breadcrumb-item active"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
@endsection

@section('content')
    <div class="page-header mt-0">
        <h1>Dashboard</h1>
    </div>
    <div class="row row-deck row-cards">
        <div class="col-sm-6 col-xl-4">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="bg-primary text-white avatar">
                                <i class="icon ti ti-user"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="font-weight-medium"><a href="{{ route('admin.users.index') }}"
                                                               class="text-body text-decoration-none stretched-link">{{ $stats->users->total }}
                                    Users</a></div>
                            <div class="text-secondary">
                                {{ $stats->users->lastWeek }} in the last week
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
