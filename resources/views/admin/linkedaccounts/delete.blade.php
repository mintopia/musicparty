@extends('layouts.app', [
    'activenav' => 'admin',
])

@section('breadcrumbs')
    @include('admin.users._breadcrumbs', [
        'user' => $account->user,
    ])
    <li class="breadcrumb-item active"><a
            href="{{ route('admin.users.accounts.delete', [$account->user->id, $account->id]) }}">Delete
            Linked {{ $account->provider->name }}Account</a>
        @endsection

        @section('content')
            <div class="page-header mt-0">
                <h1>Delete Linked {{ $account->provider->name }} Account</h1>
            </div>

            <div class="col-md-6 offset-md-3">
                <form action="{{ route('admin.users.accounts.destroy', [$account->user->id, $account->id]) }}"
                      method="post" class="card">
                    <div class="card-status-top bg-danger"></div>
                    {{ csrf_field() }}
                    {{ method_field('DELETE') }}
                    <div class="card-body text-center">
                        <i class="icon mb-4 ti ti-alert-triangle icon-lg text-danger"></i>
                        <p class="mt-4">
                            Are you sure you want to delete this <strong>{{ $account->provider->name }}</strong>
                            account?
                        </p>
                    </div>
                    <div class="card-footer text-end">
                        <div class="d-flex">
                            <a href="{{ route('admin.users.show', $account->user->id) }}"
                               class="btn btn-link">Cancel</a>
                            <button type="submit" class="btn btn-danger ms-auto">Delete</button>
                        </div>
                    </div>
                </form>
            </div>
@endsection
