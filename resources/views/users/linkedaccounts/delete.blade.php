@extends('layouts.app', [
    'activenav' => 'profile',
])

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('user.profile') }}">Profile</a></li>
    <li class="breadcrumb-item active"><a
            href="{{ route('linkedaccounts.delete', $account->id) }}">Remove Linked {{ $account->provider->name }} Account</a></li>
@endsection

@section('content')
    <div class="page-header mt-0">
        <h1>Remove Linked {{ $account->provider->name }} Account</h1>
    </div>

    <div class="col-md-6 offset-md-3">
        <form action="{{ route('linkedaccounts.destroy', $account->id) }}" method="post" class="card">
            <div class="card-status-top bg-danger"></div>
            {{ csrf_field() }}
            {{ method_field('DELETE') }}
            <div class="card-body text-center">
                <i class="icon mb-4 ti ti-alert-triangle icon-lg text-danger"></i>
                <p class="mt-4">
                    Are you sure you want to remove your <strong>{{ $account->provider->name }}</strong> account?
                </p>
            </div>
            <div class="card-footer text-end">
                <div class="d-flex">
                    <a href="{{ route('user.profile') }}" class="btn btn-link">Cancel</a>
                    <button type="submit" class="btn btn-danger ms-auto">Remove</button>
                </div>
            </div>
        </form>
    </div>
@endsection
