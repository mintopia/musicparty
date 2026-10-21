@extends('layouts.app', [
    'activenav' => "party:{$party->code}"
])

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('parties.show', $party->code) }}">{{ $party->name }}</a>
    <li class="breadcrumb-item"><a href="{{ route('parties.moderation.index', $party->code) }}">Moderation</a>
    <li class="breadcrumb-item active">
        <a href="{{ route('parties.moderation.delete', [$party->code, $moderation->id]) }}">Delete Filter</a>
    </li>
@endsection
@push('precontainer')
    @include('parties._player')
@endpush

@section('content')
    <div class="page-header mt-0">
        <h1>Delete {{ $moderation->type->getHumanReadableName() }} Filter</h1>
    </div>

    <div class="col-md-6 offset-md-3">
        <form action="{{ route('parties.moderation.destroy', [$party->code, $moderation->id]) }}" method="post" class="card">
            <div class="card-status-top bg-danger"></div>
            {{ csrf_field() }}
            {{ method_field('DELETE') }}
            <div class="card-body text-center">
                <i class="icon mb-4 ti ti-alert-triangle icon-lg text-danger"></i>
                <p class="mt-4">
                    Are you sure you want to delete this <strong>{{ $moderation->type->getHumanReadableName() }}</strong> filter for
                    <strong class="font-monospace">{{ $moderation->value }}</strong>?
                </p>
            </div>
            <div class="card-footer text-end">
                <div class="d-flex">
                    <a href="{{ route('parties.moderation.index', $party->code) }}" class="btn btn-link">Cancel</a>
                    <button type="submit" class="btn btn-danger ms-auto">Delete</button>
                </div>
            </div>
        </form>
    </div>
@endsection
