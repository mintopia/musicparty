@extends('layouts.app', [
    'activenav' => 'home',
])

@section('breadcrumbs')
    <li class="breadcrumb-item active"><a href="{{ route('home') }}">Home</a></li>
@endsection

@section('content')
    <div class="row">
        <div class="col-xl-9 col-sm-8 mb-4">
        </div>
        <div class="col-xl-3 col-sm-4 mt-sm-2">
            <div class="card">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <span class="avatar avatar-xl rounded"
                              style="background-image: url('{{ Auth::user()->avatarUrl() }}')"></span>
                    </div>
                    <div class="card-title mb-1">{{ Auth::user()->nickname }}</div>
                    <div class="text-secondary">
                        <span class="text-muted">{{ Auth::user()->getEmail() }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
