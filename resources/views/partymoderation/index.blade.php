@extends('layouts.app', [
    'activenav' => "party:{$party->code}"
])

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('parties.show', $party->code) }}">{{ $party->name }}</a>
    <li class="breadcrumb-item active"><a href="{{ route('parties.moderation.index', $party->code) }}">Moderation</a>
@endsection
@push('precontainer')
    @include('parties._player')
@endpush
@section('content')
    <div class="row">
        <div class="col page-header mt-2">
            <h1>Moderation</h1>
        </div>
        <div class="col-auto ms-auto d-print-none">
            <div class="btn-list">
                <a href="{{ route('parties.moderation.create', $party->code) }}" class="btn btn-primary d-inline-block">
                    <i class="icon ti ti-plus"></i>
                    Create Filter
                </a>
            </div>
        </div>
    </div>

    <div class="row">

        <div class="col-12">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover table-outline table-vcenter text-nowrap card-table">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Enabled</th>
                            <th>Type</th>
                            <th>Value</th>
                            <th>Regex</th>
                            <th>Notes</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($moderations as $moderation)
                            <tr>
                                <td>
                                    <a href="{{ route('parties.moderation.edit', [$party->code, $moderation->id]) }}">
                                        {{ $moderation->id }}
                                    </a>
                                </td>
                                <td>
                                    @if($moderation->enabled)
                                        <span class="status status-green">
                                        Yes
                                    </span>
                                    @else
                                        <span class="status status-red">
                                        No
                                    </span>
                                    @endif
                                </td>
                                <td>{{ $moderation->type->getHumanReadableName() }}</td>
                                <td>
                                    @if ($moderation->regex)
                                        <code>{{ $moderation->value }}</code>
                                    @else
                                        {{ $moderation->value }}
                                    @endif
                                </td>
                                <td>{{ $moderation->regex ? 'Yes' : 'No' }}</td>
                                <td>{{ $moderation->notes }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @include('partials._pagination', [
                    'page' => $moderations
                ])
            </div>
        </div>
    </div>
@endsection
