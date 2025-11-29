@extends('layouts.app', [
    'activenav' => "party:{$party->code}"
])

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('parties.show', $party->code) }}">{{ $party->name }}</a>
    <li class="breadcrumb-item"><a href="{{ route('parties.moderation.index', $party->code) }}">Moderation</a>
    <li class="breadcrumb-item active">
        <a href="{{ route('parties.moderation.create', [$party->code]) }}">Create Filter</a>
    </li>
@endsection
@push('precontainer')
    @include('parties._player')
@endpush

@section('content')
    <div class="page-header mt-0">
        <h1>Create Filter</h1>
    </div>
    <div class="row">
        <div class="{{ $spotifyData ? 'col-12 col-lg-6' : 'col-md-6 col-md-offset-3' }}">
            <form action="{{ route('parties.moderation.store', $party->code) }}" class="card" method="post">
                {{ csrf_field() }}
                {{ method_field('POST') }}
                @include('partymoderation._form')
                <div class="card-footer text-end">
                    <div class="d-flex">
                        <a href="{{ route('parties.moderation.index', $party->code) }}" class="btn btn-link">Cancel</a>
                        <button type="submit" class="btn btn-primary ms-auto">Save</button>
                    </div>
                </div>
            </form>
        </div>

        @if($spotifyData)
            <div class="col-12 col-lg-6">
                <h3>Spotify Data</h3>

                <div class="datagrid">
                    <div class="datagrid-item">
                        <div class="datagrid-title">Track ID</div>
                        <div class="datagrid-content">{{ $spotifyData->id ?? 'None' }}</div>
                    </div>
                </div>
                <div class="datagrid mt-3">
                    <div class="datagrid-item">
                        <div class="datagrid-title">Track Name</div>
                        <div class="datagrid-content">{{ $spotifyData->name ?? 'None' }}</div>
                    </div>
                </div>
                <div class="datagrid mt-3">
                    <div class="datagrid-item">
                        <div class="datagrid-title">Album ID</div>
                        <div class="datagrid-content">{{ $spotifyData->album->id ?? 'None' }}</div>
                    </div>
                </div>
                <div class="datagrid mt-3">
                    <div class="datagrid-item">
                        <div class="datagrid-title">Album Name</div>
                        <div class="datagrid-content">{{ $spotifyData->album->name ?? 'None' }}</div>
                    </div>
                </div>
                @foreach ($spotifyData->artists ?? [] as $artist)
                    <div class="datagrid mt-3">
                        <div class="datagrid-item">
                            <div class="datagrid-title">Artist ID</div>
                            <div class="datagrid-content">{{ $artist->id ?? 'None' }}</div>
                        </div>
                    </div>
                    <div class="datagrid mt-3">
                        <div class="datagrid-item">
                            <div class="datagrid-title">Artist Name</div>
                            <div class="datagrid-content">{{ $artist->name ?? 'None' }}</div>
                        </div>
                    </div>
                @endforeach

                <div class="datagrid mt-3">
                    <div class="datagrid-item">
                        <div class="datagrid-title">ISRC Code</div>
                        <div class="datagrid-content">{{ $spotifyData->external_ids->isrc ?? 'None' }}</div>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
