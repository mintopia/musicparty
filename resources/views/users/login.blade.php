@extends('layouts.login')
@section('content')
    <div class="text-center">
        <p>
            Login to join the party!
        </p>
        @foreach($providers as $provider)
            <p>
                <a href="{{ route('login.redirect', $provider->code) }}"
                   class="d-block ms-auto btn btn-{{ $provider->code }}">
                    <i class="icon ti ti-brand-{{ $provider->code }}"></i>
                    Login with {{ $provider->name }}
                </a>
            </p>
        @endforeach

    </div>
@endsection
