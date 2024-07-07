@extends('layouts.app', [
    'activenav' => "party:{$party->code}"
])

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('parties.show', $party->code) }}">{{ $party->name }}</a>
    <li class="breadcrumb-item active"><a href="{{ route('parties.edit', $party->code) }}">Settings</a>
        @endsection

@push('precontainer')
    @include('parties._player')
@endpush

@section('content')
    <div class="page-header mt-0">
        <h1>Settings</h1>
    </div>

    <div class="col-md-6 offset-md-3">
        <form action="{{ route('parties.update', $party->code) }}" method="post" class="card">
            {{ method_field('PATCH') }}
            @include('parties._form')
            <div class="card-footer text-end">
                <div class="d-flex">
                    <a href="{{ route('parties.show', $party->code) }}" class="btn btn-link">Cancel</a>
                    <button type="submit" class="btn btn-primary ms-auto">Save</button>
                </div>
            </div>
        </form>
    </div>
@endsection
