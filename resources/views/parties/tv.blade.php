@extends('layouts.tv')

@section('content')
    <div class="container-fluid">
        <tv-player
            name="{{ $party->name }}"
            code="{{ $party->code }}"
            canmanage="false"
            initialstate='@json($party->getState())'>
        </tv-player>
    </div>
@endsection
