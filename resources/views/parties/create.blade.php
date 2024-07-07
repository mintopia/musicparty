@extends('layouts.app', [
    'activenav' => 'party.create',
])

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
    <li class="breadcrumb-item active"><a href="{{ route('parties.create') }}">Create Party</a></li>
@endsection

@section('content')
    <div class="page-header mt-0">
        <h1>Create Party</h1>
    </div>

    <div class="col-md-6 offset-md-3">
        <form action="{{ route('parties.store') }}" method="post" class="card">
            @include('parties._form')
            <div class="card-footer text-end">
                <div class="d-flex">
                    <a href="{{ route('home') }}" class="btn btn-link">Cancel</a>
                    <button type="submit" class="btn btn-primary ms-auto">Save</button>
                </div>
            </div>
        </form>
    </div>
@endsection
