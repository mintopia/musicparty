@extends('layouts.app', [
    'activenav' => "party:{$party->code}"
])

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('parties.show', $party->code) }}">{{ $party->name }}</a>
    <li class="breadcrumb-item"><a href="{{ route('parties.moderation.index', $party->code) }}">Moderation</a>
    <li class="breadcrumb-item active">
        <a href="{{ route('parties.moderation.edit', [$party->code, $moderation->id]) }}">Edit Filter</a>
    </li>
@endsection
@push('precontainer')
    @include('parties._player')
@endpush

@section('content')
    <div class="page-header mt-0">
        <h1>Edit {{ $moderation->type->getHumanReadableName() }} Filter</h1>
    </div>

    <div class="col-md-6 offset-md-3">
        <form action="{{ route('parties.moderation.update', [$party->code, $moderation->id]) }}" method="post" class="card">
            {{ csrf_field() }}
            {{ method_field('PATCH') }}
            @include('partymoderation._form')
            <div class="card-footer text-end">
                <div class="d-flex">
                    <a href="{{ route('parties.moderation.index', $party->code) }}" class="btn btn-link">Cancel</a>

                    <a href="{{ route('parties.moderation.delete', [$party->code, $moderation->id]) }}" class="btn btn-link text-danger">
                        Delete
                    </a>
                    <button type="submit" class="btn ms-auto btn-primary btn-out">Save</button>
                </div>
            </div>
        </form>
    </div>
@endsection
