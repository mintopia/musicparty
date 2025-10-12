@extends('layouts.app', [
    'activenav' => "party:{$party->code}"
])

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('parties.show', $party->code) }}">{{ $party->name }}</a>
    <li class="breadcrumb-item active"><a href="{{ route('parties.youtube', $party->code) }}">Play YouTube Video</a>
        @endsection
        @push('precontainer')
            @include('parties._player')
        @endpush
        @section('content')
            <div class="col-xl-8 offset-xl-2 col-lg-10 offset-lg-1">
                <div class="page-header mt-0">
                    <h1>YouTube Player</h1>
                </div>
                <form action="{{ route('parties.youtube', $party->code) }}" method="post">
                    {{ csrf_field() }}
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <div class="input-icon">
                                <input type="text" class="form-control" name="video" id="video" placeholder="YouTube URL">
                                <span class="input-icon-addon">
                                    <i class="icon ti ti-player-play"></i>
                                </span>
                            </div>
                        </div>
                        <div class="flex-column ps-2">
                            <button type="submit" class="btn btn-primary">Play</button>
                        </div>
                    </div>
                </form>
                <pre class="mt-4 mb-4">{{ route('parties.ytplayer', ['party' => $party->code]) }}
            </div>
@endsection
